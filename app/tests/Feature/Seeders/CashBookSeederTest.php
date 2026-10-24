<?php

use App\Enums\CashBookEntryType;
use App\Models\CashBookEntry;
use App\Models\ExpenseCategory;
use Database\Seeders\CashBookSeeder;
use Database\Seeders\ExpenseCategorySeeder;

it('seeds expense categories and manual expense entries with photos spread over time', function () {
    (new ExpenseCategorySeeder())->run();
    (new CashBookSeeder())->run();

    expect(ExpenseCategory::count())->toBe(6);

    $expenses = CashBookEntry::where('type', CashBookEntryType::Pengeluaran)->get();
    expect($expenses->count())->toBeGreaterThanOrEqual(30);
    expect($expenses->whereNotNull('foto_path')->count())->toBeGreaterThan(0);
    expect($expenses->pluck('expense_category_id')->unique()->count())->toBeGreaterThan(1);

    // spread over more than one calendar month, not all dated today
    expect($expenses->pluck('date')->map->format('Y-m')->unique()->count())->toBeGreaterThan(1);
});
