<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\WebPush\HasPushSubscriptions;

#[Fillable(['name', 'email', 'phone', 'password', 'role', 'must_change_password', 'password_set_by'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPushSubscriptions, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'account_deleted_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'must_change_password' => 'boolean',
        ];
    }

    /** A store can exist before its login: no password = no account yet. */
    public function hasAccount(): bool
    {
        return $this->password !== null;
    }

    /** Logs in with phone + 6-digit PIN rather than email + password. */
    public function usesPin(): bool
    {
        return $this->email === null && $this->phone !== null;
    }

    /** How this account logs in, for display: the email, the phone (0812…), or null. */
    public function loginLabel(): ?string
    {
        return $this->email ?? ($this->phone ? '0'.substr($this->phone, 2) : null);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isSales(): bool
    {
        return $this->role === Role::Sales;
    }

    public function isUser(): bool
    {
        return $this->role === Role::User;
    }

    public function isGudang(): bool
    {
        return $this->role === Role::Gudang;
    }

    public function salesProfile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(SalesProfile::class);
    }

    public function attendanceLocations(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(AttendanceLocation::class);
    }

    /** Sales accounts not deleted (App\Support\Sales\SalesAccounts): every pick list and the Sales page. */
    public function scopeCurrentSales(Builder $query): void
    {
        $query->where('role', Role::Sales)->whereNull('account_deleted_at');
    }

    /** Sales who should be checking in: role sales, not deactivated (no profile counts as active). */
    public function scopeActiveSales(Builder $query): void
    {
        $query->where('role', Role::Sales)->whereNull('account_deleted_at')
            ->whereDoesntHave('salesProfile', fn ($p) => $p->where('is_active', false));
    }

    /** The Admin or Sales who generated this account's current temporary password. */
    public function passwordSetBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'password_set_by');
    }

    /** The stores assigned to this sales person. */
    public function assignedStores(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BuyerProfile::class, 'sales_user_id');
    }

    /** Phones running the Naraya Sales Android app (FCM push). */
    public function deviceTokens(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function buyerProfile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(BuyerProfile::class);
    }

    public function addresses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Address::class);
    }

    /** Visits logged by this user as a sales rep. */
    public function visits(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Visit::class, 'sales_id');
    }

    /** Visits made to this user's store (as a buyer). */
    public function storeVisits(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Visit::class, 'buyer_id');
    }

    /** Stores assigned to this sales user, with `last_visit_at` = that sales user's latest visit (null = never). */
    public function scopeAssignedTo(Builder $query, int $salesId): void
    {
        $query->whereHas('buyerProfile', fn (Builder $profile) => $profile->where('sales_user_id', $salesId))
            ->withMax(['storeVisits as last_visit_at' => fn (Builder $visits) => $visits->where('sales_id', $salesId)], 'visited_at')
            ->withCasts(['last_visit_at' => 'datetime']);
    }
}
