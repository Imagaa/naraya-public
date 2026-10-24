<?php

namespace App\Services\CashBook;

use App\Enums\BusinessLine;
use App\Notifications\AdminNotice;
use App\Support\ActiveLine;
use App\Support\BusinessDay;
use App\Enums\CashBookEntryKind;
use App\Enums\CashBookEntryType;
use App\Exceptions\CashBookException;
use App\Models\CashBookClosing;
use App\Models\CashBookEntry;
use App\Models\CashBookSetting;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Buku Kas rules: an immutable ledger where only the author may
 * touch a manual entry for 15 minutes, everything else is corrected by a
 * reversal posted today, and closed months are frozen. Each business line keeps
 * its own book; `$line` defaults to the active line.
 */
class Ledger
{
    public static function isClosed(CarbonInterface|string $date, ?BusinessLine $line = null): bool
    {
        return CashBookClosing::forLine($line ?? ActiveLine::current())->whereDate('month', Carbon::parse($date)->startOfMonth())->exists();
    }

    public function assertOpen(CarbonInterface|string $date, ?BusinessLine $line = null): void
    {
        if (self::isClosed($date, $line)) {
            throw new CashBookException('Bulan '.Carbon::parse($date)->locale('id')->translatedFormat('F Y').' sudah ditutup.');
        }
    }

    public function signedSum(Builder $query): int
    {
        return (int) (clone $query)->selectRaw("COALESCE(SUM(CASE WHEN type = 'pemasukan' THEN amount ELSE -amount END), 0) AS total")->value('total');
    }

    public function openingBalance(CarbonInterface $month, ?BusinessLine $line = null): int
    {
        $line ??= ActiveLine::current();
        $start = Carbon::parse($month)->startOfMonth();
        $previous = CashBookClosing::forLine($line)->whereDate('month', $start->copy()->subMonth())->first();

        return $previous ? (int) $previous->closing_balance : $this->signedSum(CashBookEntry::forLine($line)->whereDate('date', '<', $start));
    }

    /** @return array{opening: int, in: int, out: int, closing: int, breakdown: array<string, int>} */
    public function summary(CarbonInterface $month, ?BusinessLine $line = null): array
    {
        $line ??= ActiveLine::current();
        $start = Carbon::parse($month)->startOfMonth();
        $entries = CashBookEntry::forLine($line)->with('expenseCategory')->whereBetween('date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()])->get();
        $in = (int) $entries->where('type', CashBookEntryType::Pemasukan)->sum('amount');
        $out = (int) $entries->where('type', CashBookEntryType::Pengeluaran)->sum('amount');
        $opening = $this->openingBalance($start, $line);

        $breakdown = $entries->groupBy(fn (CashBookEntry $e) => ($e->type === CashBookEntryType::Pemasukan ? 'Masuk' : 'Keluar').' · '.match (true) {
            $e->kind === CashBookEntryKind::Rekap => $e->source?->label() ?? 'Rekap',
            $e->kind === CashBookEntryKind::Koreksi => 'Koreksi',
            default => $e->expenseCategory?->name ?? 'Lainnya',
        })->map(fn ($g) => (int) $g->sum('amount'))->sortKeys()->all();

        return ['opening' => $opening, 'in' => $in, 'out' => $out, 'closing' => $opening + $in - $out, 'breakdown' => $breakdown];
    }

    public function record(array $data, User $by): CashBookEntry
    {
        $this->guardData($data);

        return CashBookEntry::create([
            'line' => ActiveLine::current(),
            'type' => $data['type'],
            'kind' => CashBookEntryKind::Manual,
            'amount' => (int) $data['amount'],
            'date' => Carbon::parse($data['date'])->toDateString(),
            'expense_category_id' => $data['type'] === CashBookEntryType::Pengeluaran ? $data['expense_category_id'] : null,
            'catatan' => trim($data['catatan']),
            'foto_path' => $data['foto_path'] ?? null,
            'user_id' => $by->id,
        ]);
    }

    public function update(CashBookEntry $entry, array $data, User $by): void
    {
        $this->guardEditable($entry, $by);
        $merged = array_merge([
            'type' => $entry->type, 'amount' => $entry->amount, 'date' => $entry->date,
            'expense_category_id' => $entry->expense_category_id, 'catatan' => $entry->catatan,
        ], $data);
        $this->guardData($merged, $entry->line);

        $entry->update([
            'type' => $merged['type'],
            'amount' => (int) $merged['amount'],
            'date' => Carbon::parse($merged['date'])->toDateString(),
            'expense_category_id' => $merged['type'] === CashBookEntryType::Pengeluaran ? $merged['expense_category_id'] : null,
            'catatan' => trim($merged['catatan']),
            'foto_path' => array_key_exists('foto_path', $data) ? $data['foto_path'] : $entry->foto_path,
            'edited_at' => now(),
            'edited_by' => $by->id,
        ]);
    }

    public function delete(CashBookEntry $entry, User $by): void
    {
        $this->guardEditable($entry, $by);

        DB::transaction(function () use ($entry, $by) {
            $entry->update(['deleted_by' => $by->id]);
            $entry->delete();
        });
    }

    /** $newAmount null = cancel the entry fully; otherwise post the difference. Always dated today. */
    public function correct(CashBookEntry $entry, ?int $newAmount, string $reason, User $by): CashBookEntry
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new CashBookException('Tulis alasan koreksi.');
        }
        $diff = ($newAmount ?? 0) - (int) $entry->amount;
        if ($diff === 0) {
            throw new CashBookException('Nominal baru sama dengan nominal lama.');
        }
        $this->assertOpen(BusinessDay::today(), $entry->line);

        // Lowering an income is money out; lowering an expense is money back in.
        $type = $diff < 0
            ? ($entry->type === CashBookEntryType::Pemasukan ? CashBookEntryType::Pengeluaran : CashBookEntryType::Pemasukan)
            : $entry->type;

        return CashBookEntry::create([
            'line' => $entry->line,
            'type' => $type,
            'kind' => CashBookEntryKind::Koreksi,
            'amount' => abs($diff),
            'date' => BusinessDay::today()->toDateString(),
            'expense_category_id' => $entry->expense_category_id,
            'corrects_id' => $entry->id,
            'correction_reason' => $reason,
            'catatan' => 'Koreksi untuk '.$entry->date->locale('id')->translatedFormat('j M Y').': '.$reason,
            'user_id' => $by->id,
        ]);
    }

    public function canCloseManually(CarbonInterface $month, ?BusinessLine $line = null): bool
    {
        $start = Carbon::parse($month)->startOfMonth();

        return ! self::isClosed($start, $line)
            && ($start->lt(BusinessDay::now()->startOfMonth()) || ($start->format('Y-m') === BusinessDay::now()->format('Y-m') && BusinessDay::now()->isLastOfMonth()));
    }

    public function close(CarbonInterface $month, ?User $by, ?BusinessLine $line = null): CashBookClosing
    {
        $line ??= ActiveLine::current();
        $start = Carbon::parse($month)->startOfMonth();

        if (self::isClosed($start, $line)) {
            throw new CashBookException('Bulan ini sudah ditutup.');
        }
        $now = BusinessDay::now();
        if ($start->format('Y-m') >= $now->format('Y-m') && ! ($start->format('Y-m') === $now->format('Y-m') && $now->isLastOfMonth())) {
            throw new CashBookException('Bulan berjalan baru bisa ditutup di hari terakhirnya.');
        }
        if (CashBookClosing::forLine($line)->exists() && ! self::isClosed($start->copy()->subMonth(), $line)) {
            throw new CashBookException('Tutup buku bulan sebelumnya dulu.');
        }

        $s = $this->summary($start, $line);

        return CashBookClosing::create([
            'line' => $line,
            'month' => $start->toDateString(),
            'opening_balance' => $s['opening'],
            'total_in' => $s['in'],
            'total_out' => $s['out'],
            'closing_balance' => $s['closing'],
            'breakdown' => $s['breakdown'],
            'closed_by' => $by?->id,
            'auto' => $by === null,
            'closed_at' => now(),
        ]);
    }

    /** Scheduler: from the closing day on, every month before the current one is closed; older months always. Each line. */
    public function closeDue(): int
    {
        $now = BusinessDay::now();
        $until = Carbon::parse(($now->day >= CashBookSetting::current()->closing_day ? $now->copy()->startOfMonth() : $now->copy()->startOfMonth()->subMonth())->format('Y-m-d'));
        $closed = 0;

        foreach (BusinessLine::cases() as $line) {
            $last = CashBookClosing::forLine($line)->max('month');
            $firstEntry = CashBookEntry::withTrashed()->forLine($line)->min('date');
            $first = $last ? Carbon::parse($last)->addMonth() : ($firstEntry ? Carbon::parse($firstEntry) : null);

            for ($month = $first?->copy()->startOfMonth(); $month?->lt($until); $month->addMonth()) {
                $this->close($month, null, $line);
                $closed++;
            }
        }
        if ($closed > 0) {
            AdminNotice::toAdmins('Buku kas ditutup', $closed.' bulan ditutup otomatis (tutup buku tanggal '.CashBookSetting::current()->closing_day.')', route('admin.cash-book.index', absolute: false), 'book-lock');
        }

        return $closed;
    }

    private function guardEditable(CashBookEntry $entry, User $by): void
    {
        if ($entry->kind !== CashBookEntryKind::Manual || $entry->user_id !== $by->id) {
            throw new CashBookException('Hanya pencatatnya yang bisa mengubah transaksi ini; gunakan Koreksi.');
        }
        if (! $entry->isWithinEditWindow()) {
            throw new CashBookException('Lewat '.CashBookEntry::EDIT_MINUTES.' menit — gunakan Koreksi.');
        }
        $this->assertOpen($entry->date, $entry->line);
    }

    private function guardData(array $data, ?BusinessLine $line = null): void
    {
        if ((int) $data['amount'] < 1) {
            throw new CashBookException('Nominal minimal Rp 1.');
        }
        if (Carbon::parse($data['date'])->toDateString() > BusinessDay::today()->toDateString()) {
            throw new CashBookException('Tanggal tidak boleh di masa depan.');
        }
        if ($data['type'] === CashBookEntryType::Pengeluaran && empty($data['expense_category_id'])) {
            throw new CashBookException('Pilih kategori pengeluaran.');
        }
        if (trim((string) $data['catatan']) === '') {
            throw new CashBookException('Tulis keterangan.');
        }
        $this->assertOpen($data['date'], $line);
    }
}
