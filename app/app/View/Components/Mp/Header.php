<?php

namespace App\View\Components\Mp;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Order;
use App\Support\Cart;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

class Header extends Component
{
    public ?string $storeName = null;
    public ?string $address = null;
    public ?string $salesName = null;
    public ?string $salesPhone = null;
    public int $points = 0;
    public int $shippedCount = 0;
    public int $cartCount;
    public int $cartTotal;
    public Collection $categories;

    /**
     * $search and $active come from the page component, not the request: on
     * Livewire update requests the request is /livewire/update, which would
     * blank the search box and the tab highlight after every cart action.
     *
     * $title turns on the phone task bar (back, title, cart) used by flow
     * pages; from 640px the normal header shows instead.
     */
    public function __construct(
        public bool $tabbar = true,
        public string $search = '',
        public ?string $active = null,
        public ?string $title = null,
        public ?string $subtitle = null,
        public ?string $back = null,
    )
    {
        $user = auth()->user();
        $profile = $user?->buyerProfile;
        $sales = $profile?->salesUser;

        $this->storeName = $user?->name;
        $this->address = $profile?->address;
        $this->salesName = $sales?->name;
        $this->salesPhone = $sales?->salesProfile?->phone;
        $this->points = (int) ($profile?->loyalty_points ?? 0);
        $this->shippedCount = $user
            ? Order::where('buyer_id', $user->id)->where('status', OrderStatus::Shipped->value)->count()
            : 0;
        $this->cartCount = Cart::count();
        $this->cartTotal = Cart::total();
        $this->categories = \App\Support\Catalog::categories(); // non-empty only
    }

    public function render(): View
    {
        return view('components.mp.header');
    }
}
