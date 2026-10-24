<?php

use App\Enums\CashBookEntryKind;
use App\Enums\CashBookEntryType;
use App\Enums\Role;
use App\Exceptions\CashBookException;
use App\Models\CashBookClosing;
use App\Models\CashBookEntry;
use App\Models\CashBookSetting;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\CashBook\Ledger;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-20 10:00'));
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->ledger = app(Ledger::class);
    $this->cat = ExpenseCategory::factory()->create();
});

function entry(string $date, int $amount, CashBookEntryType $type = CashBookEntryType::Pemasukan, array $extra = []): CashBookEntry
{
    return CashBookEntry::factory()->create(['date' => $date, 'amount' => $amount, 'type' => $type] + $extra);
}

it('computes opening, in, out and closing for a month', function () {
    entry('2026-09-10', 1000);
    entry('2026-09-11', 300, CashBookEntryType::Pengeluaran);
    entry('2026-10-02', 500);
    entry('2026-10-03', 200, CashBookEntryType::Pengeluaran);

    expect($this->ledger->summary(Carbon::parse('2026-10-01')))
        ->toMatchArray(['opening' => 700, 'in' => 500, 'out' => 200, 'closing' => 1000]);
});

it('leaves deleted entries out of balances', function () {
    entry('2026-09-10', 1000);
    entry('2026-09-11', 400)->delete();

    expect($this->ledger->openingBalance(Carbon::parse('2026-10-01')))->toBe(1000);
});

it('records a manual entry and refuses future dates, closed months and expenses without a category', function () {
    $e = $this->ledger->record(['type' => CashBookEntryType::Pengeluaran, 'amount' => 50000, 'date' => '2026-10-20', 'expense_category_id' => $this->cat->id, 'catatan' => 'Bensin'], $this->admin);
    expect($e)->kind->toBe(CashBookEntryKind::Manual)->user_id->toBe($this->admin->id);

    expect(fn () => $this->ledger->record(['type' => CashBookEntryType::Pemasukan, 'amount' => 1, 'date' => '2026-10-21', 'catatan' => 'x'], $this->admin))->toThrow(CashBookException::class, 'masa depan');
    expect(fn () => $this->ledger->record(['type' => CashBookEntryType::Pengeluaran, 'amount' => 1, 'date' => '2026-10-20', 'catatan' => 'x'], $this->admin))->toThrow(CashBookException::class, 'kategori');

    CashBookClosing::create(['month' => '2026-09-01', 'opening_balance' => 0, 'total_in' => 0, 'total_out' => 0, 'closing_balance' => 0, 'breakdown' => [], 'auto' => true, 'closed_at' => now()]);
    expect(fn () => $this->ledger->record(['type' => CashBookEntryType::Pemasukan, 'amount' => 1, 'date' => '2026-09-30', 'catatan' => 'x'], $this->admin))->toThrow(CashBookException::class, 'sudah ditutup');
});

it('lets only the author change or delete an entry, and only for 15 minutes', function () {
    $e = $this->ledger->record(['type' => CashBookEntryType::Pemasukan, 'amount' => 1000, 'date' => '2026-10-20', 'catatan' => 'Jual kardus'], $this->admin);
    $other = User::factory()->create(['role' => Role::Admin]);

    expect(fn () => $this->ledger->update($e, ['amount' => 900], $other))->toThrow(CashBookException::class, 'pencatatnya');

    $this->ledger->update($e, ['amount' => 900, 'catatan' => 'Jual kardus bekas'], $this->admin);
    expect($e->fresh())->amount->toBe(900)->edited_by->toBe($this->admin->id);

    $this->travel(16)->minutes();
    expect(fn () => $this->ledger->delete($e->fresh(), $this->admin))->toThrow(CashBookException::class, 'Koreksi');
});

it('soft deletes inside the window and remembers who', function () {
    $e = $this->ledger->record(['type' => CashBookEntryType::Pemasukan, 'amount' => 1000, 'date' => '2026-10-20', 'catatan' => 'x'], $this->admin);

    $this->ledger->delete($e, $this->admin);

    expect(CashBookEntry::find($e->id))->toBeNull()->and(CashBookEntry::withTrashed()->find($e->id)->deleted_by)->toBe($this->admin->id);
});

it('refuses to move an entry into a closed month', function () {
    CashBookClosing::create(['month' => '2026-09-01', 'opening_balance' => 0, 'total_in' => 0, 'total_out' => 0, 'closing_balance' => 0, 'breakdown' => [], 'auto' => true, 'closed_at' => now()]);
    $e = $this->ledger->record(['type' => CashBookEntryType::Pemasukan, 'amount' => 1000, 'date' => '2026-10-20', 'catatan' => 'x'], $this->admin);

    expect(fn () => $this->ledger->update($e, ['date' => '2026-09-30'], $this->admin))->toThrow(CashBookException::class, 'sudah ditutup');
});

it('posts the reversal in the right direction', function (CashBookEntryType $original, ?int $newAmount, CashBookEntryType $expectedType, int $expectedAmount) {
    $e = entry('2026-10-01', 500, $original, ['expense_category_id' => $this->cat->id]);

    $k = $this->ledger->correct($e, $newAmount, 'Salah input', $this->admin);

    expect($k)->kind->toBe(CashBookEntryKind::Koreksi)->type->toBe($expectedType)->amount->toBe($expectedAmount)
        ->corrects_id->toBe($e->id)->date->toDateString()->toBe('2026-10-20');
})->with([
    'cancel income' => [CashBookEntryType::Pemasukan, null, CashBookEntryType::Pengeluaran, 500],
    'reduce income' => [CashBookEntryType::Pemasukan, 450, CashBookEntryType::Pengeluaran, 50],
    'raise income' => [CashBookEntryType::Pemasukan, 600, CashBookEntryType::Pemasukan, 100],
    'reduce expense' => [CashBookEntryType::Pengeluaran, 450, CashBookEntryType::Pemasukan, 50],
    'cancel expense' => [CashBookEntryType::Pengeluaran, null, CashBookEntryType::Pemasukan, 500],
]);

it('requires a reason and a real change to correct', function () {
    $e = entry('2026-10-01', 500);

    expect(fn () => $this->ledger->correct($e, 500, 'x', $this->admin))->toThrow(CashBookException::class, 'sama');
    expect(fn () => $this->ledger->correct($e, null, ' ', $this->admin))->toThrow(CashBookException::class, 'alasan');
});

it('notes the original date when correcting an entry from a closed month', function () {
    $e = entry('2026-09-15', 500);
    CashBookClosing::create(['month' => '2026-09-01', 'opening_balance' => 0, 'total_in' => 500, 'total_out' => 0, 'closing_balance' => 500, 'breakdown' => [], 'auto' => true, 'closed_at' => now()]);

    $k = $this->ledger->correct($e, null, 'Dobel', $this->admin);

    expect($k->catatan)->toBe('Koreksi untuk 15 Sep 2026: Dobel')->and($k->date->toDateString())->toBe('2026-10-20');
});

it('closes a month with frozen numbers and opens the next one from them', function () {
    entry('2026-09-10', 1000);
    entry('2026-09-11', 300, CashBookEntryType::Pengeluaran, ['expense_category_id' => $this->cat->id]);

    $closing = $this->ledger->close(Carbon::parse('2026-09-01'), $this->admin);

    expect($closing)->opening_balance->toBe(0)->total_in->toBe(1000)->total_out->toBe(300)->closing_balance->toBe(700)->auto->toBeFalse();
    entry('2026-09-12', 99999, CashBookEntryType::Pemasukan); // sneaked in afterwards (direct insert): the frozen number wins
    expect($this->ledger->openingBalance(Carbon::parse('2026-10-01')))->toBe(700);
});

it('only lets admin close a past month, or the current one on its last day', function () {
    expect($this->ledger->canCloseManually(Carbon::parse('2026-09-01')))->toBeTrue()
        ->and($this->ledger->canCloseManually(Carbon::parse('2026-10-01')))->toBeFalse();
    $this->travelTo(Carbon::parse('2026-10-31 18:00'));
    expect($this->ledger->canCloseManually(Carbon::parse('2026-10-01')))->toBeTrue();
});

it('requires the previous month closed once any month was closed', function () {
    entry('2026-07-10', 100);
    $this->ledger->close(Carbon::parse('2026-07-01'), $this->admin);

    expect(fn () => $this->ledger->close(Carbon::parse('2026-09-01'), $this->admin))->toThrow(CashBookException::class, 'sebelumnya');
});

it('auto closes every due month on the closing day, oldest first, and not before', function () {
    entry('2026-08-10', 100);
    entry('2026-09-10', 200);
    $this->travelTo(Carbon::parse('2026-10-04 00:15'));
    expect($this->ledger->closeDue())->toBe(1); // August is overdue; September waits for the 5th

    $this->travelTo(Carbon::parse('2026-10-05 00:15'));
    expect($this->ledger->closeDue())->toBe(1);
    expect(CashBookClosing::orderBy('month')->pluck('closing_balance')->all())->toBe([100, 300]);
    expect(CashBookClosing::first()->auto)->toBeTrue();
});

it('honours a changed closing day', function () {
    entry('2026-09-10', 200);
    CashBookSetting::current()->update(['closing_day' => 10]);
    $this->travelTo(Carbon::parse('2026-10-09 00:15'));

    expect($this->ledger->closeDue())->toBe(0);
});
