<?php

namespace App\Models;

use App\Support\Catalog;
use App\Support\ProductSearch;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/** A buyer's recent storefront searches, which also feed "Pencarian populer". */
#[Fillable(['user_id', 'term'])]
class SearchHistory extends Model
{
    public const KEEP = 8;

    public static function remember(User $user, string $term): void
    {
        $term = Str::limit(Str::lower(Str::squish($term)), 60, '');
        if ($term === '') {
            return;
        }

        static::updateOrCreate(['user_id' => $user->id, 'term' => $term])->touch();

        $keep = static::where('user_id', $user->id)->latest('updated_at')->latest('id')->limit(self::KEEP)->pluck('id');
        static::where('user_id', $user->id)->whereNotIn('id', $keep)->delete();
    }

    /** @return list<string> newest first */
    public static function recent(User $user): array
    {
        return static::where('user_id', $user->id)->latest('updated_at')->latest('id')->limit(self::KEEP)->pluck('term')->all();
    }

    /**
     * Terms at least two buyers searched in the last 30 days that still find
     * products, most-searched first; topped up with the biggest categories
     * so the list is never empty on a quiet shop.
     *
     * @return list<string>
     */
    public static function popular(int $limit = 6): array
    {
        return Cache::remember('search-popular:'.\App\Support\ActiveLine::current()->value.':'.$limit, 600, function () use ($limit) {
            $terms = static::where('updated_at', '>=', now()->subDays(30))
                ->selectRaw('term, COUNT(*) AS buyers')
                ->groupBy('term')
                ->havingRaw('COUNT(*) >= 2')
                ->orderByDesc('buyers')
                ->orderBy('term')
                ->limit($limit * 3)
                ->pluck('term')
                ->filter(fn (string $term) => ProductSearch::rank($term) !== [])
                ->take($limit);

            $categories = Catalog::categories()->sortBy([['products_count', 'desc'], ['name', 'asc']])->pluck('name');

            return $terms->concat($categories)->unique(fn ($term) => Str::lower($term))->take($limit)->values()->all();
        });
    }
}
