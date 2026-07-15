<?php

namespace App\Http\Controllers;

use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => (new CartResource($this->cart($request)))->resolve($request),
        ]);
    }

    public function storeItem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'string'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);
        $product = Product::find($data['product_id']);

        if (! $product || ! $product->is_active) {
            return response()->json(['message' => 'El producto no está disponible.'], 404);
        }

        $cart = $this->cart($request);
        $items = collect($cart->items ?? []);
        $existing = $items->firstWhere('product_id', (string) $product->getKey());
        $quantity = (int) $data['quantity'] + (int) ($existing['quantity'] ?? 0);

        if ($quantity > 100 || $quantity > $product->stock) {
            return response()->json(['message' => 'No hay existencias suficientes para esa cantidad.'], 422);
        }

        if ($existing) {
            $items = $items->map(function (array $item) use ($product, $quantity): array {
                if ($item['product_id'] === (string) $product->getKey()) {
                    $item['quantity'] = $quantity;
                }

                return $item;
            });
        } else {
            $items->push([
                'product_id' => (string) $product->getKey(),
                'quantity' => $quantity,
                'added_at' => now(),
            ]);
        }

        $cart->update(['items' => $items->values()->all(), 'currency' => $product->currency]);

        return $this->response($request, $cart->fresh(), 'Producto agregado al carrito.');
    }

    public function updateItem(Request $request, string $productId): JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);
        $cart = $this->cart($request);
        $items = collect($cart->items ?? []);

        if (! $items->contains('product_id', $productId)) {
            return response()->json(['message' => 'El producto no está en el carrito.'], 404);
        }

        $product = Product::find($productId);

        if (! $product || ! $product->is_active) {
            return response()->json(['message' => 'El producto no está disponible.'], 422);
        }

        if ($data['quantity'] > $product->stock) {
            return response()->json(['message' => 'No hay existencias suficientes para esa cantidad.'], 422);
        }

        $cart->update([
            'items' => $items->map(function (array $item) use ($productId, $data): array {
                if ($item['product_id'] === $productId) {
                    $item['quantity'] = $data['quantity'];
                }

                return $item;
            })->values()->all(),
        ]);

        return $this->response($request, $cart->fresh(), 'Cantidad actualizada.');
    }

    public function destroyItem(Request $request, string $productId): JsonResponse
    {
        $cart = $this->cart($request);
        $items = collect($cart->items ?? []);

        if (! $items->contains('product_id', $productId)) {
            return response()->json(['message' => 'El producto no está en el carrito.'], 404);
        }

        $cart->update([
            'items' => $items->reject(fn (array $item): bool => $item['product_id'] === $productId)->values()->all(),
        ]);

        return $this->response($request, $cart->fresh(), 'Producto eliminado del carrito.');
    }

    public function clear(Request $request): JsonResponse
    {
        $cart = $this->cart($request);
        $cart->update(['items' => []]);

        return $this->response($request, $cart->fresh(), 'Carrito vaciado.');
    }

    private function cart(Request $request): Cart
    {
        return Cart::firstOrCreate(
            ['user_id' => (string) $request->user()->getKey()],
            ['items' => [], 'currency' => 'MXN'],
        );
    }

    private function response(Request $request, Cart $cart, string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'data' => (new CartResource($cart))->resolve($request),
        ]);
    }
}
