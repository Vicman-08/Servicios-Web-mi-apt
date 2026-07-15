<?php

namespace App\Http\Controllers;

use App\Http\Resources\InventoryMovementResource;
use App\Http\Resources\ProductResource;
use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Throwable;

class InventoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'product_id' => ['nullable', 'string'],
            'type' => ['nullable', Rule::in(['sale', 'cancellation', 'restock', 'adjustment'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $movements = InventoryMovement::query()
            ->when($filters['product_id'] ?? null, fn ($query, string $productId) => $query->where('product_id', $productId))
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 30)
            ->withQueryString();

        return InventoryMovementResource::collection($movements);
    }

    public function show(InventoryMovement $inventoryMovement): InventoryMovementResource
    {
        return new InventoryMovementResource($inventoryMovement);
    }

    public function adjust(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'string'],
            'quantity_delta' => ['required', 'integer', 'between:-1000000,1000000', 'not_in:0'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $product = Product::find($data['product_id']);

        if (! $product) {
            return response()->json(['message' => 'El producto solicitado no existe.'], 404);
        }

        $delta = (int) $data['quantity_delta'];
        $stockBefore = (int) $product->stock;

        if ($delta < 0) {
            $updated = Product::where('_id', $product->getKey())
                ->where('stock', '>=', abs($delta))
                ->decrement('stock', abs($delta), ['updated_at' => now()]);
        } else {
            $updated = Product::where('_id', $product->getKey())
                ->increment('stock', $delta, ['updated_at' => now()]);
        }

        if ($updated !== 1) {
            return response()->json(['message' => 'El ajuste dejaría una existencia negativa.'], 422);
        }

        try {
            $movement = InventoryMovement::create([
                'product_id' => (string) $product->getKey(),
                'order_id' => null,
                'user_id' => (string) $request->user()->getKey(),
                'type' => $delta > 0 ? 'restock' : 'adjustment',
                'quantity_delta' => $delta,
                'stock_before' => $stockBefore,
                'stock_after' => $stockBefore + $delta,
                'reason' => trim($data['reason']),
                'metadata' => ['source' => 'admin-api'],
            ]);
        } catch (Throwable $exception) {
            $delta > 0
                ? Product::where('_id', $product->getKey())->decrement('stock', $delta, ['updated_at' => now()])
                : Product::where('_id', $product->getKey())->increment('stock', abs($delta), ['updated_at' => now()]);

            throw $exception;
        }

        return response()->json([
            'message' => 'Existencia ajustada correctamente.',
            'data' => [
                'product' => (new ProductResource($product->fresh()))->resolve($request),
                'movement' => (new InventoryMovementResource($movement))->resolve($request),
            ],
        ], 201);
    }
}
