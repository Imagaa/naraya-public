<?php

namespace App\Livewire\Admin\Sales;

use App\Enums\Role;
use App\Models\User;
use App\Support\Sales\SalesPerformance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Url;
use Livewire\Component;
use App\Livewire\Admin\Concerns\HasAdminShell;

class Form extends Component
{
    use HasAdminShell;
    public ?User $sales = null;

    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $phone = '';
    public int $monthlyTarget = 0;
    public bool $isActive = true;

    #[Url(as: 'bulan')]
    public string $perfMonth = '';

    public function mount(?User $sales = null): void
    {
        $this->sales = $sales;
        $this->perfMonth = preg_match('/^\d{4}-\d{2}$/', $this->perfMonth) ? $this->perfMonth : now()->format('Y-m');

        if ($sales?->exists) {
            $this->name = $sales->name;
            $this->email = $sales->email;
            $this->phone = (string) $sales->salesProfile?->phone;
            $this->monthlyTarget = $sales->salesProfile?->monthly_target ?? 0;
            $this->isActive = $sales->salesProfile?->is_active ?? true;
        }
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.($this->sales->id ?? 'NULL'),
            'password' => $this->sales?->exists ? 'nullable|min:8' : 'required|min:8',
            'phone' => 'nullable|string|max:30',
            'monthlyTarget' => 'required|integer|min:0',
        ]);

        if ($this->sales?->exists) {
            $this->sales->update([
                'name' => $this->name,
                'email' => $this->email,
                ...($this->password ? ['password' => Hash::make($this->password)] : []),
            ]);
            $user = $this->sales;
        } else {
            $user = User::create([
                'name' => $this->name,
                'email' => $this->email,
                'password' => Hash::make($this->password),
                'role' => Role::Sales,
            ]);
        }

        $user->salesProfile()->updateOrCreate([], [
            'phone' => $this->phone ?: null,
            'monthly_target' => $this->monthlyTarget,
            'joined_at' => $user->salesProfile?->joined_at ?? now(),
            'is_active' => $this->isActive,
        ]);

        \App\Support\SalesAreas::refresh($user->id); // the area is made from this sales person's stores

        return redirect()->route('admin.sales.index');
    }

    /** Delete this sales account; their records keep their name (App\Support\Sales\SalesAccounts). */
    public function deleteAccount()
    {
        abort_unless($this->sales?->exists, 404);
        \App\Support\Sales\SalesAccounts::delete($this->sales);
        session()->flash('status', $this->sales->name.' dihapus. Riwayatnya tetap tercatat.');

        return redirect()->route('admin.sales.index');
    }

    public function render()
    {
        $month = preg_match('/^\d{4}-\d{2}$/', $this->perfMonth) ? Carbon::createFromFormat('Y-m-d', $this->perfMonth.'-01') : now();

        return view('livewire.admin.sales.form', [
            'performance' => $this->sales?->exists ? app(SalesPerformance::class)->month($this->sales, $month) : null,
            'perfLabel' => $month->locale('id')->translatedFormat('F Y'),
            'area' => $this->sales?->salesProfile?->area,
        ])->layout('layouts.admin');
    }
}
