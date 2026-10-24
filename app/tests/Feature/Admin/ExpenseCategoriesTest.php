<?php

use App\Enums\Role;
use App\Livewire\Admin\ExpenseCategories\Index;
use App\Models\ExpenseCategory;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
});

it('lists expense categories', function () {
    $category = ExpenseCategory::factory()->create(['name' => 'Listrik']);

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->assertSee('Listrik');
});

it('adds a new expense category', function () {
    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->set('newCategoryName', 'Transport')
        ->call('addCategory');

    expect(ExpenseCategory::where('name', 'Transport')->exists())->toBeTrue();
});

it('toggles a category active state', function () {
    $category = ExpenseCategory::factory()->create(['is_active' => true]);

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->call('toggleActive', $category->id);

    expect($category->fresh()->is_active)->toBeFalse();
});

it('blocks non-admin roles from the expense categories index', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);

    $this->actingAs($sales)
        ->get('http://admin.'.config('app.domain').'/expense-categories')
        ->assertStatus(403);
});
