<?php

namespace App\Http\Controllers;

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MongoDB\BSON\Decimal128;
use Throwable;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Order::orderBy('created_at', 'desc');

        if ($request->user()->role !== 'admin') {
            $query->where('user_id', (string) $request->user()->getKey());
        }

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'string'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $product = Product::find($data['product_id']);

        if (! $product || ! $product->is_active) {
            return response()->json(['message' => 'El producto no está disponible.'], 404);
        }

        $stockBefore = $product->stock;
        $updated = Product::where('_id', $product->getKey())
            ->where('is_active', true)
            ->where('stock', '>=', $data['quantity'])
            ->decrement('stock', $data['quantity'], ['updated_at' => now()]);

        if ($updated !== 1) {
            return response()->json(['message' => 'No hay existencias suficientes.'], 422);
        }

        $unitPrice = (string) $product->price;
        $subtotal = number_format(((float) $unitPrice) * $data['quantity'], 2, '.', '');

        try {
            $order = Order::create([
                'user_id' => (string) $request->user()->getKey(),
                'items' => [[
                    'product_id' => (string) $product->getKey(),
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'unit_price' => new Decimal128($unitPrice),
                    'quantity' => $data['quantity'],
                    'subtotal' => new Decimal128($subtotal),
                ]],
                'total' => $subtotal,
                'currency' => $product->currency,
                'status' => 'completed',
            ]);

            InventoryMovement::create([
                'product_id' => (string) $product->getKey(),
                'order_id' => (string) $order->getKey(),
                'user_id' => (string) $request->user()->getKey(),
                'type' => 'sale',
                'quantity_delta' => -$data['quantity'],
                'stock_before' => $stockBefore,
                'stock_after' => $stockBefore - $data['quantity'],
            ]);
        } catch (Throwable $exception) {
            Product::where('_id', $product->getKey())->increment('stock', $data['quantity'], ['updated_at' => now()]);
            throw $exception;
        }

        return response()->json($order, 201);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        if ($request->user()->role !== 'admin' && $order->user_id !== (string) $request->user()->getKey()) {
            abort(403, 'No tienes permiso para consultar esta compra.');
        }

        return response()->json($order);
    }

    public function destroy(Request $request, Order $order): JsonResponse
    {
        if ($request->user()->role !== 'admin' && $order->user_id !== (string) $request->user()->getKey()) {
            abort(403, 'No tienes permiso para cancelar esta compra.');
        }

        $cancelled = Order::where('_id', $order->getKey())
            ->where('status', 'completed')
            ->update(['status' => 'cancelled', 'updated_at' => now()]);

        if ($cancelled !== 1) {
            return response()->json(['message' => 'La compra ya fue cancelada.'], 422);
        }

        $item = $order->items[0];
        $product = Product::find($item['product_id']);

        if ($product) {
            $stockBefore = $product->stock;
            Product::where('_id', $product->getKey())->increment('stock', $item['quantity'], ['updated_at' => now()]);

            InventoryMovement::create([
                'product_id' => (string) $product->getKey(),
                'order_id' => (string) $order->getKey(),
                'user_id' => (string) $request->user()->getKey(),
                'type' => 'cancellation',
                'quantity_delta' => $item['quantity'],
                'stock_before' => $stockBefore,
                'stock_after' => $stockBefore + $item['quantity'],
            ]);
        }

        return response()->json(['message' => 'Compra cancelada y existencia restaurada.']);
    }
}
