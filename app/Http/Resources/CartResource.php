<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $subtotalInCents = 0;
        $itemCount = 0;

        $items = collect($this->items ?? [])->map(function (array $item) use (&$subtotalInCents, &$itemCount): array {
            $product = Product::find($item['product_id']);
            $quantity = (int) ($item['quantity'] ?? 0);
            $itemCount += $quantity;

            if (! $product) {
                return [
                    'product_id' => (string) $item['product_id'],
                    'sku' => null,
                    'name' => 'Producto no disponible',
                    'unit_price' => '0.00',
                    'currency' => $this->currency ?? 'MXN',
                    'stock' => 0,
                    'quantity' => $quantity,
                    'subtotal' => '0.00',
                    'is_available' => false,
                ];
            }

            $unitPriceInCents = (int) round(((float) $product->price) * 100);
            $itemSubtotalInCents = $unitPriceInCents * $quantity;
            $subtotalInCents += $itemSubtotalInCents;

            return [
                'product_id' => (string) $product->getKey(),
                'sku' => $product->sku,
                'name' => $product->name,
                'unit_price' => $this->money($unitPriceInCents),
                'currency' => $product->currency,
                'stock' => (int) $product->stock,
                'quantity' => $quantity,
                'subtotal' => $this->money($itemSubtotalInCents),
                'is_available' => (bool) $product->is_active && $quantity > 0 && $quantity <= $product->stock,
            ];
        })->values()->all();

        return [
            'id' => (string) $this->getKey(),
            'user_id' => $this->user_id,
            'currency' => $this->currency ?? 'MXN',
            'items' => $items,
            'item_count' => $itemCount,
            'subtotal' => $this->money($subtotalInCents),
            'total' => $this->money($subtotalInCents),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
