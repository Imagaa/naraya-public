<?php

use App\Enums\CashBookEntryType;
use App\Enums\Role;
use App\Models\CashBookEntry;
use App\Models\ExpenseCategory;
use App\Models\Order;
use App\Models\User;

it('creates an expense category', function () {
    $category = ExpenseCategory::factory()->create(['name' => 'Listrik']);

    expect($category->is_active)->toBeTrue();
});

it('creates a cash book entry linked to a category and user', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $category = ExpenseCategory::factory()->create();

    $entry = CashBookEntry::factory()->create([
        'type' => CashBookEntryType::Pengeluaran,
        'amount' => 50000,
        'expense_category_id' => $category->id,
        'user_id' => $admin->id,
    ]);

    expect($entry->type)->toBe(CashBookEntryType::Pengeluaran);
    expect($entry->expenseCategory->is($category))->toBeTrue();
    expect($entry->user->is($admin))->toBeTrue();
});

it('allows a system-generated entry with no user', function () {
    $entry = CashBookEntry::factory()->create([
        'type' => CashBookEntryType::Pemasukan,
        'user_id' => null,
        'reference' => 'Order #1',
    ]);

    expect($entry->user)->toBeNull();
    expect($entry->reference)->toBe('Order #1');
});
