<?php

namespace App\Observers;

use App\Models\Product;
use App\Notifications\AdminNotice;
use App\Notifications\BuyerNotice;

class ProductObserver
{
    /**
     * "Back in stock" means buyable again — stock reached the minimum
     * order — matching the card's "Stok habis", not merely stock > 0.
     * getOriginal() still holds the pre-save values inside `updated`.
     */
    public function updated(Product $product): void
    {
        if ($product->wasChanged('stock') && (int) $product->stock === 0 && (int) $product->getOriginal('stock') > 0 && $product->is_active) {
            AdminNotice::toAdmins('Stok habis', "{$product->name} habis", route('admin.products.edit', $product, absolute: false), 'package', 'warning', urgent: true);
        }

        $min = (int) $product->min_stock;
        if ($product->wasChanged('stock') && $product->is_active && $min > 0 && (int) $product->stock > 0
            && (int) $product->stock < $min && (int) $product->getOriginal('stock') >= $min) {
            AdminNotice::toAdmins('Stok menipis', "{$product->name} tinggal {$product->qtyLabel((int) $product->stock)} (batas {$min})", route('admin.products.edit', $product, absolute: false), 'package', 'warning');
        }

        if (! $product->wasChanged('stock') || ! $product->is_active || ! $product->isAvailable()) {
            return;
        }

        $wasAvailable = (int) $product->getOriginal('stock') >= max(1, (int) $product->getOriginal('min_purchase')) * $product->ppu();

        if ($wasAvailable) {
            return;
        }

        $alerts = $product->stockAlerts()->with('user')->get();
        $url = route('products.show', $product, absolute: false);

        foreach ($alerts as $alert) {
            $alert->user->notify(new BuyerNotice('Stok tersedia lagi', "{$product->name} tersedia lagi", $url, 'package-check', 'success'));
        }

        $product->stockAlerts()->whereKey($alerts->modelKeys())->delete();
    }
}
