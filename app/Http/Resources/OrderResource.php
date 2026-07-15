<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use MongoDB\BSON\Decimal128;

class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->getKey(),
            'user_id' => $this->user_id,
            'items' => collect($this->items ?? [])->map(function (array $item): array {
                return [
                    ...$item,
                    'unit_price' => $this->decimal($item['unit_price'] ?? 0),
                    'subtotal' => $this->decimal($item['subtotal'] ?? 0),
                ];
            })->values()->all(),
            'subtotal' => $this->decimal($this->subtotal ?? $this->total),
            'tax' => $this->decimal($this->tax ?? 0),
            'shipping_cost' => $this->decimal($this->shipping_cost ?? 0),
            'total' => $this->decimal($this->total),
            'currency' => $this->currency,
            'status' => $this->status,
            'payment_status' => $this->payment_status ?? 'pending',
            'shipping_address' => $this->shipping_address,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function decimal(mixed $value): string
    {
        if ($value instanceof Decimal128) {
            return (string) $value;
        }

        return number_format((float) $value, 2, '.', '');
    }
}
