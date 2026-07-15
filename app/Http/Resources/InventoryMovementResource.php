<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->getKey(),
            'product_id' => $this->product_id,
            'order_id' => $this->order_id,
            'user_id' => $this->user_id,
            'type' => $this->type,
            'quantity_delta' => (int) $this->quantity_delta,
            'stock_before' => (int) $this->stock_before,
            'stock_after' => (int) $this->stock_after,
            'reason' => $this->reason,
            'metadata' => $this->metadata ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
