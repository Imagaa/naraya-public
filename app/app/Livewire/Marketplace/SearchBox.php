<?php

namespace App\Livewire\Marketplace;

use App\Models\Product;
use App\Models\SearchHistory;
use App\Support\ProductSearch;
use Livewire\Component;

/**
 * The header search: live fuzzy results while typing, recent and popular
 * searches while empty. Still a plain GET form to /produk, so Enter works
 * before Livewire boots.
 */
class SearchBox extends Component
{
    public string $q = '';

    public function visit(int $id)
    {
        $this->rememberSearch();

        return $this->redirect(Product::forLine(\App\Support\ActiveLine::current())->where('is_active', true)->findOrFail($id)->url());
    }

    public function forget(string $term): void
    {
        if ($user = $this->buyer()) {
            SearchHistory::where('user_id', $user->id)->where('term', $term)->delete();
        }
    }

    public function clearHistory(): void
    {
        if ($user = $this->buyer()) {
            SearchHistory::where('user_id', $user->id)->delete();
        }
    }

    public function render()
    {
        $term = trim($this->q);
        $ids = $term === '' ? [] : array_keys(ProductSearch::rank($term));
        $top = array_slice($ids, 0, 6);
        $buyer = $this->buyer();

        return view('livewire.marketplace.search-box', [
            'term' => $term,
            'total' => count($ids),
            'results' => Product::forLine(\App\Support\ActiveLine::current())->with('category')->whereIn('id', $top)->get()->sortBy(fn (Product $p) => array_search($p->id, $top))->values(),
            'history' => $term === '' && $buyer ? SearchHistory::recent($buyer) : [],
            'popular' => $term === '' ? SearchHistory::popular() : [],
        ]);
    }

    private function rememberSearch(): void
    {
        if ($user = $this->buyer()) {
            SearchHistory::remember($user, $this->q);
        }
    }

    private function buyer()
    {
        $user = auth()->user();

        return $user?->isUser() ? $user : null;
    }
}
