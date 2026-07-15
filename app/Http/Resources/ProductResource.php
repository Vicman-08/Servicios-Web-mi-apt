<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->getKey(),
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (string) $this->price,
            'currency' => $this->currency,
            'stock' => (int) $this->stock,
            'is_active' => (bool) $this->is_active,
            'category_id' => $this->category_id,
            'images' => $this->images ?? [],
            'tags' => $this->tags ?? [],
            'attributes' => $this->attributes ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
