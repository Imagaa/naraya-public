<?php

use App\Support\SalesAreas;
use Illuminate\Database\Migrations\Migration;

/** Areas now come from each sales person's store pins (App\Support\SalesAreas): rebuild them, drop the admin-made ones. */
return new class extends Migration
{
    public function up(): void
    {
        SalesAreas::refreshAll();
    }

    public function down(): void
    {
        // the old hand-made areas are gone; nothing to restore
    }
};
