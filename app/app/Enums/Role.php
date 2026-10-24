<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Sales = 'sales';
    case Gudang = 'gudang';
    case User = 'user';

    /** The subdomain this role's portal lives on — the single source of truth for post-login redirects. */
    public function portalDomain(): string
    {
        return match ($this) {
            self::Admin => config('app.admin_domain'),
            self::Sales => config('app.sales_domain'),
            self::Gudang => config('app.staff_domain'), // the gudang portal was removed on 2026-10-23; the role is dormant
            self::User => config('app.domain'),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Sales => 'Sales',
            self::Gudang => 'Gudang',
            self::User => 'Pembeli',
        };
    }

    /** The staff role whose portal answers on this host (its own door and session), or null for any other host. */
    public static function staffPortalFor(string $host): ?self
    {
        foreach ([self::Admin, self::Sales] as $role) {
            if ($host === $role->portalDomain()) {
                return $role;
            }
        }

        return null;
    }
}
