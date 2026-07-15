<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiInteractionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->getKey(),
            'user_id' => $this->user_id,
            'query' => $this->query,
            'response' => $this->response,
            'provider' => $this->provider,
            'model' => $this->model,
            'status' => $this->status,
            'duration_ms' => (int) ($this->duration_ms ?? 0),
            'metadata' => $this->metadata ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
