<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Services\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __invoke(Request $request, CheckoutService $checkout): JsonResponse
    {
        $order = $checkout->createOrder($request->user());

        return response()->json([
            'message' => 'Compra confirmada correctamente.',
            'data' => (new OrderResource($order))->resolve($request),
        ], 201);
    }
}
