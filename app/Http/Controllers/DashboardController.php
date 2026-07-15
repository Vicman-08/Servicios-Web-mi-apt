<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MongoDB\BSON\Decimal128;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $revenue = Order::where('payment_status', 'paid')
            ->where('status', '!=', 'cancelled')
            ->sum('total');

        return response()->json([
            'data' => [
                'users' => [
                    'total' => User::count(),
                    'buyers' => User::where('role', 'buyer')->count(),
                    'administrators' => User::where('role', 'admin')->count(),
                    'active' => User::where('status', 'active')->count(),
                ],
                'products' => [
                    'total' => Product::count(),
                    'active' => Product::where('is_active', true)->count(),
                    'low_stock' => Product::where('stock', '<=', 5)->count(),
                    'out_of_stock' => Product::where('stock', 0)->count(),
                ],
                'orders' => [
                    'total' => Order::count(),
                    'pending' => Order::where('status', 'pending')->count(),
                    'completed' => Order::whereIn('status', ['completed', 'delivered'])->count(),
                    'cancelled' => Order::where('status', 'cancelled')->count(),
                    'revenue' => $this->decimal($revenue),
                    'currency' => 'MXN',
                ],
                'inventory_movements' => InventoryMovement::count(),
                'recent_orders' => OrderResource::collection(
                    Order::orderBy('created_at', 'desc')->limit(5)->get(),
                )->resolve($request),
            ],
        ]);
    }

    private function decimal(mixed $value): string
    {
        if ($value instanceof Decimal128) {
            return (string) $value;
        }

        return number_format((float) $value, 2, '.', '');
    }
}
