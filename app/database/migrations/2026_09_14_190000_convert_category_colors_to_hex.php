<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * categories.color used to store a design-system tag variant name
     * (accent/accent-2/success/neutral/danger, a fixed 5-color palette).
     * Admin now picks from a much wider hex palette, so every category —
     * including the ones already seeded — needs to store a real hex value.
     * Maps each old variant to the hex it was already visually rendering
     * as, so existing categories don't change color.
     */
    public function up(): void
    {
        $map = [
            'accent' => '#F0B90B',
            'accent-2' => '#A8863F',
            'success' => '#7A8A5E',
            'neutral' => '#9C8C67',
            'danger' => '#C1603A',
        ];

        foreach ($map as $variant => $hex) {
            DB::table('categories')->where('color', $variant)->update(['color' => $hex]);
        }
    }

    public function down(): void
    {
        $map = [
            '#F0B90B' => 'accent',
            '#A8863F' => 'accent-2',
            '#7A8A5E' => 'success',
            '#9C8C67' => 'neutral',
            '#C1603A' => 'danger',
        ];

        foreach ($map as $hex => $variant) {
            DB::table('categories')->where('color', $hex)->update(['color' => $variant]);
        }
    }
};
