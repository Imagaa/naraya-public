<?php

namespace App\Livewire\Admin\ExpenseCategories;

use App\Models\ExpenseCategory;
use Livewire\Component;
use App\Livewire\Admin\Concerns\HasAdminShell;

class Index extends Component
{
    use HasAdminShell;
    public string $newCategoryName = '';

    public function addCategory()
    {
        $this->validate(['newCategoryName' => 'required|string|max:255']);

        ExpenseCategory::create(['name' => $this->newCategoryName]);

        $this->newCategoryName = '';
    }

    public function toggleActive(int $categoryId)
    {
        $category = ExpenseCategory::findOrFail($categoryId);
        $category->update(['is_active' => ! $category->is_active]);
    }

    public function render()
    {
        return view('livewire.admin.expense-categories.index', [
            'categories' => ExpenseCategory::orderBy('name')->get(),
        ])->layout('layouts.admin');
    }
}
