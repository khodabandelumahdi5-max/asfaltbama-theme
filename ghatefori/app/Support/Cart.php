<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Collection;

/** Session cart: [product_id => qty]. */
class Cart
{
    private const KEY = 'cart';

    public static function add(Product $product, int $qty = 1): void
    {
        $cart = session(self::KEY, []);
        $cart[$product->id] = min(99, ($cart[$product->id] ?? 0) + max(1, $qty));
        session([self::KEY => $cart]);
    }

    public static function set(Product $product, int $qty): void
    {
        $cart = session(self::KEY, []);
        if ($qty < 1) {
            unset($cart[$product->id]);
        } else {
            $cart[$product->id] = min(99, $qty);
        }
        session([self::KEY => $cart]);
    }

    public static function clear(): void
    {
        session()->forget(self::KEY);
    }

    public static function count(): int
    {
        return array_sum(session(self::KEY, []));
    }

    /** @return Collection<int, array{product: Product, qty: int, subtotal: int}> */
    public static function lines(): Collection
    {
        $cart = session(self::KEY, []);

        return Product::with('brand')->whereIn('id', array_keys($cart))->get()
            ->map(fn (Product $p) => ['product' => $p, 'qty' => $cart[$p->id], 'subtotal' => (int) $p->price * $cart[$p->id]]);
    }

    public static function shippingFor(?string $city): array
    {
        $rates = config('shop.shipping');

        return $rates[$city] ?? $rates['*'];
    }
}
