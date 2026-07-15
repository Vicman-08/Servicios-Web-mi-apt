<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return $this->paginated($request, adminFilters: false);
    }

    public function adminIndex(Request $request): AnonymousResourceCollection
    {
        return $this->paginated($request, adminFilters: true);
    }

    private function paginated(Request $request, bool $adminFilters): AnonymousResourceCollection
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['pending', 'confirmed', 'completed', 'shipped', 'delivered', 'cancelled'])],
            'payment_status' => ['nullable', Rule::in(['pending', 'paid', 'failed', 'refunded'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $query = Order::query();

        if (! $adminFilters || $request->user()->role !== 'admin') {
            $query->where('user_id', (string) $request->user()->getKey());
        } else {
            $query->when($data['user_id'] ?? null, fn ($query, string $userId) => $query->where('user_id', $userId))
                ->when($data['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
                ->when($data['payment_status'] ?? null, fn ($query, string $status) => $query->where('payment_status', $status));
        }

        return OrderResource::collection(
            $query->orderBy('created_at', 'desc')
                ->paginate($data['per_page'] ?? 20)
                ->withQueryString(),
        );
    }

    public function show(Request $request, Order $order): OrderResource
    {
        if ($request->user()->role !== 'admin' && $order->user_id !== (string) $request->user()->getKey()) {
            abort(403, 'No tienes permiso para consultar esta compra.');
        }

        return new OrderResource($order);
    }

    public function destroy(Request $request, Order $order): JsonResponse
    {
        if ($request->user()->role !== 'admin' && $order->user_id !== (string) $request->user()->getKey()) {
            abort(403, 'No tienes permiso para cancelar esta compra.');
        }

        return $this->cancelOrder($request, $order);
    }

    public function updateStatus(Request $request, Order $order): JsonResponse|OrderResource
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['confirmed', 'completed', 'shipped', 'delivered', 'cancelled'])],
        ]);

        if ($data['status'] === $order->status) {
            return new OrderResource($order);
        }

        if ($data['status'] === 'cancelled') {
            return $this->cancelOrder($request, $order);
        }

        $allowedTransitions = [
            'pending' => ['confirmed'],
            'confirmed' => ['completed', 'shipped'],
            'completed' => ['shipped'],
            'shipped' => ['delivered'],
            'delivered' => [],
            'cancelled' => [],
        ];

        if (! in_array($data['status'], $allowedTransitions[$order->status] ?? [], true)) {
            return response()->json([
                'message' => "No se puede cambiar una orden de {$order->status} a {$data['status']}.",
            ], 422);
        }

        $order->update(['status' => $data['status']]);

        return new OrderResource($order->fresh());
    }

    private function cancelOrder(Request $request, Order $order): JsonResponse
    {
        $cancelled = Order::where('_id', $order->getKey())
            ->where('status', '!=', 'cancelled')
            ->update(['status' => 'cancelled', 'updated_at' => now()]);

        if ($cancelled !== 1) {
            return response()->json(['message' => 'La compra ya fue cancelada.'], 422);
        }

        foreach ($order->items as $item) {
            $product = Product::find($item['product_id']);

            if (! $product) {
                continue;
            }

            $stockBefore = (int) $product->stock;
            $quantity = (int) $item['quantity'];
            Product::where('_id', $product->getKey())->increment('stock', $quantity, ['updated_at' => now()]);

            InventoryMovement::create([
                'product_id' => (string) $product->getKey(),
                'order_id' => (string) $order->getKey(),
                'user_id' => (string) $request->user()->getKey(),
                'type' => 'cancellation',
                'quantity_delta' => $quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $stockBefore + $quantity,
                'reason' => 'Cancelación de compra',
            ]);
        }

        return response()->json([
            'message' => 'Compra cancelada y existencia restaurada.',
            'data' => (new OrderResource($order->fresh()))->resolve($request),
        ]);
    }
}
