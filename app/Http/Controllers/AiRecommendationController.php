<?php

namespace App\Http\Controllers;

use App\Exceptions\AiServiceException;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\GeminiRecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiRecommendationController extends Controller
{
    public function __invoke(Request $request, GeminiRecommendationService $recommendations): JsonResponse
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:500'],
        ]);
        $products = Product::where('is_active', true)
            ->where('stock', '>', 0)
            ->orderBy('name')
            ->limit(50)
            ->get();

        if ($products->isEmpty()) {
            return response()->json(['message' => 'No hay productos disponibles para recomendar.'], 422);
        }

        try {
            $result = $recommendations->recommend(
                trim($data['query']),
                $products,
                $request->user('sanctum'),
            );
        } catch (AiServiceException $exception) {
            return response()->json(['message' => $exception->getMessage()], 503);
        }

        $recommendedProducts = collect($result['recommended_product_ids'])
            ->map(fn (string $id) => $products->first(fn ($product): bool => (string) $product->getKey() === $id))
            ->filter()
            ->values();

        return response()->json([
            'data' => [
                'answer' => $result['answer'],
                'recommendations' => ProductResource::collection($recommendedProducts)->resolve($request),
                'provider' => $result['provider'],
                'model' => $result['model'],
            ],
        ]);
    }
}
