<?php

namespace App\Http\Controllers;

use App\Http\Resources\AiInteractionResource;
use App\Models\AiInteraction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class AiInteractionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['success', 'error'])],
            'provider' => ['nullable', 'string', 'max:50'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return AiInteractionResource::collection(
            AiInteraction::query()
                ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
                ->when($filters['provider'] ?? null, fn ($query, string $provider) => $query->where('provider', $provider))
                ->orderBy('created_at', 'desc')
                ->paginate($filters['per_page'] ?? 30)
                ->withQueryString(),
        );
    }
}
