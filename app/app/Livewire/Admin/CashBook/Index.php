<?php

namespace App\Livewire\Admin\CashBook;

use App\Support\BusinessDay;
use App\Enums\CashBookEntryKind;
use App\Enums\CashBookEntryType;
use App\Exceptions\CashBookException;
use App\Livewire\Admin\Concerns\HasAdminShell;
use App\Models\CashBookClosing;
use App\Models\CashBookEntry;
use App\Models\ExpenseCategory;
use App\Services\CashBook\DailyPoster;
use App\Services\CashBook\Ledger;
use App\Support\ActiveLine;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Buku Kas: one month at a time, an immutable ledger. */
class Index extends Component
{
    use HasAdminShell, WithFileUploads;

    public const BIG_AMOUNT = 5_000_000;

    #[Url(as: 'bulan')]
    public string $month = '';

    #[Url(as: 'jenis')]
    public string $filter = 'all';

    public string $search = '';

    public ?int $categoryFilter = null;

    // entry dialog
    public ?int $editingId = null;

    public string $step = 'form';

    public string $formType = 'pengeluaran';

    public $formAmount = '';

    /** Bumped on every open so the amount field (Alpine-formatted) remounts empty. */
    public int $formKey = 0;

    public string $formDate = '';

    public ?int $formCategoryId = null;

    public string $formNote = '';

    public $formPhoto;

    public bool $confirmed = false;

    // correction dialog
    public ?int $correctingId = null;

    public string $correctionMode = 'full';

    public $correctionAmount = '';

    public string $correctionReason = '';

    public function mount(): void
    {
        $this->month = preg_match('/^\d{4}-\d{2}$/', $this->month) ? $this->month : BusinessDay::now()->format('Y-m');
    }

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['all', 'masuk', 'keluar', 'koreksi'], true) ? $filter : 'all';
    }

    public function openCreate(): void
    {
        $this->resetEntryForm();
        $this->dispatch('cash-open', name: 'entry');
    }

    public function openEdit(int $id): void
    {
        $e = CashBookEntry::findOrFail($id);
        $this->resetEntryForm();
        $this->editingId = $e->id;
        $this->formType = $e->type->value;
        $this->formAmount = (string) $e->amount;
        $this->formDate = $e->date->toDateString();
        $this->formCategoryId = $e->expense_category_id;
        $this->formNote = (string) $e->catatan;
        $this->formKey++;
        $this->dispatch('cash-open', name: 'entry');
    }

    public function review(): void
    {
        $this->validate([
            'formType' => 'required|in:pemasukan,pengeluaran',
            'formAmount' => 'required|integer|min:1',
            'formDate' => 'required|date|before_or_equal:'.BusinessDay::today()->toDateString(),
            'formCategoryId' => 'required_if:formType,pengeluaran|nullable|exists:expense_categories,id',
            'formNote' => 'required|string|max:500',
            'formPhoto' => 'nullable|image|max:5120',
        ], [
            'formAmount.required' => 'Isi nominal.',
            'formNote.required' => 'Tulis keterangan.',
            'formCategoryId.required_if' => 'Pilih kategori pengeluaran.',
            'formDate.before_or_equal' => 'Tanggal tidak boleh di masa depan.',
        ]);
        $this->step = 'confirm';
    }

    public function back(): void
    {
        $this->step = 'form';
        $this->confirmed = false;
    }

    public function save(Ledger $ledger): void
    {
        $this->validate(['confirmed' => 'accepted'], ['confirmed.accepted' => 'Centang dulu bahwa nominal dan tanggal sudah diperiksa.']);

        $data = [
            'type' => CashBookEntryType::from($this->formType),
            'amount' => (int) $this->formAmount,
            'date' => $this->formDate,
            'expense_category_id' => $this->formCategoryId,
            'catatan' => $this->formNote,
        ];
        if ($this->formPhoto) {
            $data['foto_path'] = $this->formPhoto->store('cash-book', 's3');
        }

        try {
            $this->editingId
                ? $ledger->update(CashBookEntry::findOrFail($this->editingId), $data, auth()->user())
                : $ledger->record($data, auth()->user());
        } catch (CashBookException $e) {
            $this->addError('cash', $e->getMessage());

            return;
        }

        $this->resetEntryForm();
        $this->dispatch('cash-close', name: 'entry');
    }

    public function delete(Ledger $ledger, int $id): void
    {
        try {
            $ledger->delete(CashBookEntry::findOrFail($id), auth()->user());
        } catch (CashBookException $e) {
            $this->addError('cash', $e->getMessage());
        }
    }

    public function openCorrection(int $id): void
    {
        $this->reset('correctionMode', 'correctionAmount', 'correctionReason');
        $this->resetErrorBag();
        $this->correctingId = CashBookEntry::findOrFail($id)->id;
        $this->dispatch('cash-open', name: 'correction');
    }

    public function saveCorrection(Ledger $ledger): void
    {
        $this->validate([
            'correctionMode' => 'required|in:full,amount',
            'correctionAmount' => 'required_if:correctionMode,amount|nullable|integer|min:0',
            'correctionReason' => 'required|string|max:500',
        ], ['correctionReason.required' => 'Tulis alasan koreksi.', 'correctionAmount.required_if' => 'Isi nominal yang benar.']);

        try {
            $ledger->correct(CashBookEntry::findOrFail($this->correctingId), $this->correctionMode === 'full' ? null : (int) $this->correctionAmount, $this->correctionReason, auth()->user());
        } catch (CashBookException $e) {
            $this->addError('correctionReason', $e->getMessage());

            return;
        }

        $this->correctingId = null;
        $this->dispatch('cash-close', name: 'correction');
    }

    public function closeMonth(Ledger $ledger): void
    {
        try {
            $ledger->close($this->monthStart(), auth()->user());
        } catch (CashBookException $e) {
            $this->addError('cash', $e->getMessage());

            return;
        }
        $this->dispatch('cash-close', name: 'close');
    }

    public function render()
    {
        $ledger = app(Ledger::class);
        $poster = app(DailyPoster::class);
        $start = $this->monthStart();
        $summary = $ledger->summary($start);

        // Running saldo over the whole month (filters only hide rows, never change the balance).
        $running = $summary['opening'];
        $rows = CashBookEntry::forLine(ActiveLine::current())->with(['expenseCategory', 'user', 'items.subject', 'corrections', 'corrects'])
            ->whereBetween('date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()])
            ->orderBy('date')->orderBy('id')->get()
            ->map(function (CashBookEntry $e) use (&$running) {
                $running += $e->signedAmount();

                return ['entry' => $e, 'saldo' => $running];
            })
            ->reverse()->values()
            ->filter(fn ($r) => match ($this->filter) {
                'masuk' => $r['entry']->type === CashBookEntryType::Pemasukan,
                'keluar' => $r['entry']->type === CashBookEntryType::Pengeluaran,
                'koreksi' => $r['entry']->kind === CashBookEntryKind::Koreksi,
                default => true,
            })
            ->filter(fn ($r) => ! $this->categoryFilter || $r['entry']->expense_category_id === $this->categoryFilter)
            ->filter(fn ($r) => trim($this->search) === '' || str_contains(mb_strtolower((string) $r['entry']->catatan), mb_strtolower(trim($this->search))))
            ->values();

        return view('livewire.admin.cash-book.index', [
            'start' => $start,
            'summary' => $summary,
            'rows' => $rows,
            'closing' => CashBookClosing::forLine(ActiveLine::current())->with('closedBy')->whereDate('month', $start)->first(),
            'isCurrent' => $start->format('Y-m') === BusinessDay::now()->format('Y-m'),
            'canClose' => $ledger->canCloseManually($start),
            'today' => $start->format('Y-m') === BusinessDay::now()->format('Y-m') ? $poster->summarize(BusinessDay::today(), ActiveLine::current()) : [],
            'categories' => ExpenseCategory::where('is_active', true)->orderBy('name')->get(),
            'correcting' => $this->correctingId ? CashBookEntry::find($this->correctingId) : null,
        ])->layout('layouts.admin');
    }

    private function monthStart(): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $this->month.'-01')->startOfDay();
    }

    private function resetEntryForm(): void
    {
        $this->reset('editingId', 'step', 'formType', 'formAmount', 'formCategoryId', 'formNote', 'formPhoto', 'confirmed');
        $this->resetErrorBag();
        $this->formDate = BusinessDay::today()->toDateString();
        $this->formKey++;
    }
}
