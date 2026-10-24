<?php

namespace App\Livewire\Admin\Buyers;

use App\Enums\BuyerStatus;
use App\Enums\Role;
use App\Livewire\Admin\Concerns\HasAdminShell;
use App\Livewire\Concerns\CapturesStorefront;
use App\Models\Area;
use App\Models\SalesNote;
use App\Models\User;
use App\Models\Visit;
use App\Notifications\BuyerNotice;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Form extends Component
{
    use CapturesStorefront, HasAdminShell;

    public ?User $buyer = null;

    public string $name = '';
    public string $ownerName = '';
    public string $phone = '';
    public string $address = '';
    public ?int $areaId = null;
    public ?int $salesUserId = null;
    public bool $isActive = true;

    public function mount(?User $buyer = null): void
    {
        $this->buyer = $buyer;

        if ($buyer?->exists) {
            $profile = $buyer->buyerProfile;
            $this->name = $buyer->name;
            $this->ownerName = (string) $profile?->owner_name;
            $this->phone = (string) $profile?->phone;
            $this->address = (string) $profile?->address;
            $this->areaId = $profile?->area_id;
            $this->salesUserId = $profile?->sales_user_id;
            $this->isActive = $profile?->is_active ?? true;
            $this->lat = $profile?->latitude !== null ? (float) $profile->latitude : null;
            $this->lng = $profile?->longitude !== null ? (float) $profile->longitude : null;
        }
    }

    public function save()
    {
        $isNew = ! $this->buyer?->exists;
        $existingPhoto = $this->buyer?->buyerProfile?->store_photo_path;

        $this->validate([
            'name' => 'required|string|max:255',
            'ownerName' => 'required|string|max:255',
            'phone' => 'required|string|max:30',
            'address' => 'required|string|max:500',
            'areaId' => 'nullable|exists:areas,id',
            'salesUserId' => 'nullable|exists:users,id',
            ...$this->storefrontRules(photoRequired: $existingPhoto === null),
        ], $this->storefrontMessages());

        $user = DB::transaction(function () use ($isNew, $existingPhoto) {
            $user = $isNew
                ? User::create(['name' => $this->name, 'role' => Role::User]) // the login is made in the "Akun toko" panel
                : tap($this->buyer)->update(['name' => $this->name]);

            $areaId = $this->areaId ?? Area::nearestTo($this->lat, $this->lng)?->id;

            $user->buyerProfile()->updateOrCreate([], [
                'owner_name' => $this->ownerName,
                'phone' => Phone::normalize($this->phone),
                'address' => $this->address,
                'latitude' => $this->lat,
                'longitude' => $this->lng,
                'store_photo_path' => $this->storeStorefrontPhoto() ?? $existingPhoto,
                'area_id' => $areaId,
                'sales_user_id' => $this->salesUserId ?? ($isNew ? Area::find($areaId)?->activeSalesProfile()?->user_id : null),
                'is_active' => $this->isActive,
            ]);

            return $user;
        });

        // a new store opens on its own page, with the account panel ready
        return $isNew ? redirect()->route('admin.buyers.edit', ['buyer' => $user, 'akun' => 1]) : redirect()->route('admin.buyers.index');
    }

    /** Sales chosen in the approval panel; null means the area's active sales. */
    public ?int $approveSalesId = null;

    public string $rejectionReason = '';

    /** Approve a self-registration; the sales defaults to the active sales of the buyer's area. */
    public function approve(): void
    {
        $profile = $this->buyer->buyerProfile;
        abort_unless($profile?->status === BuyerStatus::Pending, 403);

        $profile->update([
            'status' => BuyerStatus::Approved,
            'sales_user_id' => $this->approveSalesId ?? $profile->sales_user_id ?? Area::find($profile->area_id)?->activeSalesProfile()?->user_id,
            'rejection_reason' => null,
        ]);

        $this->buyer->notify(new BuyerNotice('Akun disetujui', 'Akun toko Anda sudah disetujui. Selamat berbelanja!', '/', 'circle-check', 'success'));
        $this->buyer->refresh();
    }

    public function reject(): void
    {
        $profile = $this->buyer->buyerProfile;
        abort_unless($profile?->status === BuyerStatus::Pending, 403);

        // An owner asking for the login of a store a sales already registered: refusing drops the
        // login only, the store itself stays as it was.
        if ($profile->sales_user_id) {
            DB::transaction(function () use ($profile) {
                $this->buyer->forceFill(['email' => null, 'phone' => null, 'password' => null, 'must_change_password' => false, 'remember_token' => null])->save();
                $profile->update(['status' => BuyerStatus::Approved, 'rejection_reason' => null]);
            });
            $this->buyer->refresh();

            return;
        }

        $this->validate(['rejectionReason' => 'required|string|max:500'], ['rejectionReason.required' => 'Alasan penolakan wajib diisi.']);

        $profile->update(['status' => BuyerStatus::Rejected, 'rejection_reason' => $this->rejectionReason]);
        $this->buyer->refresh();
    }

    /**
     * Sales tied to this store: the assigned one first, then anyone who visited
     * (a note always logs a visit) in the last 90 days.
     *
     * @return \Illuminate\Support\Collection<int, array{user: User, assigned: bool, visits: int, last: ?\Illuminate\Support\Carbon}>
     */
    private function relatedSales()
    {
        $visits = Visit::where('buyer_id', $this->buyer->id)->where('visited_at', '>=', now()->subDays(90))
            ->selectRaw('sales_id, COUNT(*) as visits, MAX(visited_at) as last')->groupBy('sales_id')->get()->keyBy('sales_id');
        $assignedId = $this->buyer->buyerProfile?->sales_user_id;
        $ids = $visits->keys()->push($assignedId)->filter()->unique();

        return User::with('salesProfile')->whereKey($ids)->get()
            ->map(fn (User $u) => [
                'user' => $u,
                'assigned' => $u->id === $assignedId,
                'visits' => (int) ($visits[$u->id]->visits ?? 0),
                'last' => isset($visits[$u->id]) ? \Illuminate\Support\Carbon::parse($visits[$u->id]->last) : null,
            ])
            ->sortBy([['assigned', 'desc'], ['visits', 'desc']])->values();
    }

    public function render()
    {
        return view('livewire.admin.buyers.form', [
            'areas' => Area::orderBy('name')->get(),
            'salesUsers' => User::currentSales()->orderBy('name')->get(),
            'storePhotoUrl' => $this->buyer?->buyerProfile?->storePhotoUrl(),
            'pending' => $this->buyer?->buyerProfile?->status === BuyerStatus::Pending,
            // a pending store that a sales already registered = the owner asking for its login
            'claim' => $this->buyer?->buyerProfile?->status === BuyerStatus::Pending && $this->buyer->buyerProfile->sales_user_id,
            'relatedSales' => $this->buyer?->exists ? $this->relatedSales() : collect(),
            'notes' => $this->buyer?->exists
                ? SalesNote::where('buyer_id', $this->buyer->id)->with('salesUser:id,name')->latest('id')->take(15)->get()
                : collect(),
            'areaSales' => $this->buyer?->buyerProfile?->area?->activeSalesProfile()?->user,
        ])->layout('layouts.admin');
    }
}
