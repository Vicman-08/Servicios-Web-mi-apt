<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use MongoDB\BSON\Decimal128;
use Throwable;

class CheckoutService
{
    public function createOrder(User $user): Order
    {
        $cart = Cart::where('user_id', (string) $user->getKey())->first();

        if (! $cart || empty($cart->items)) {
            throw ValidationException::withMessages(['cart' => 'El carrito está vacío.']);
        }

        $products = [];
        $currency = null;

        foreach ($cart->items as $item) {
            $product = Product::find($item['product_id']);
            $quantity = (int) ($item['quantity'] ?? 0);

            if (! $product || ! $product->is_active) {
                throw ValidationException::withMessages(['cart' => 'Uno de los productos ya no está disponible.']);
            }

            if ($quantity < 1 || $quantity > 100) {
                throw ValidationException::withMessages(['cart' => 'Una cantidad del carrito no es válida.']);
            }

            if ($currency !== null && $currency !== $product->currency) {
                throw ValidationException::withMessages(['cart' => 'Todos los productos deben usar la misma moneda.']);
            }

            $currency = $product->currency;
            $products[] = ['product' => $product, 'quantity' => $quantity];
        }

        $adjusted = [];
        $order = null;

        try {
            $orderItems = [];
            $subtotalInCents = 0;

            foreach ($products as $entry) {
                /** @var Product $product */
                $product = $entry['product'];
                $quantity = $entry['quantity'];
                $updated = Product::where('_id', $product->getKey())
                    ->where('is_active', true)
                    ->where('stock', '>=', $quantity)
                    ->decrement('stock', $quantity, ['updated_at' => now()]);

                if ($updated !== 1) {
                    throw ValidationException::withMessages([
                        'cart' => "No hay existencias suficientes de {$product->name}.",
                    ]);
                }

                $adjusted[] = ['product_id' => (string) $product->getKey(), 'quantity' => $quantity];
                $unitPriceInCents = (int) round(((float) $product->price) * 100);
                $itemSubtotalInCents = $unitPriceInCents * $quantity;
                $subtotalInCents += $itemSubtotalInCents;
                $orderItems[] = [
                    'product_id' => (string) $product->getKey(),
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'unit_price' => new Decimal128($this->money($unitPriceInCents)),
                    'quantity' => $quantity,
                    'subtotal' => new Decimal128($this->money($itemSubtotalInCents)),
                ];
            }

            $total = $this->money($subtotalInCents);
            $order = Order::create([
                'user_id' => (string) $user->getKey(),
                'items' => $orderItems,
                'subtotal' => $total,
                'tax' => '0.00',
                'shipping_cost' => '0.00',
                'total' => $total,
                'currency' => $currency ?? 'MXN',
                'status' => 'confirmed',
                'payment_status' => 'paid',
            ]);

            foreach ($adjusted as $entry) {
                $product = Product::findOrFail($entry['product_id']);
                $stockAfter = (int) $product->stock;
                InventoryMovement::create([
                    'product_id' => (string) $product->getKey(),
                    'order_id' => (string) $order->getKey(),
                    'user_id' => (string) $user->getKey(),
                    'type' => 'sale',
                    'quantity_delta' => -$entry['quantity'],
                    'stock_before' => $stockAfter + $entry['quantity'],
                    'stock_after' => $stockAfter,
                ]);
            }

            $cart->update(['items' => []]);

            return $order->fresh();
        } catch (Throwable $exception) {
            if ($order) {
                InventoryMovement::where('order_id', (string) $order->getKey())->delete();
                $order->delete();
            }

            foreach ($adjusted as $entry) {
                Product::where('_id', $entry['product_id'])
                    ->increment('stock', $entry['quantity'], ['updated_at' => now()]);
            }

            throw $exception;
        }
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
