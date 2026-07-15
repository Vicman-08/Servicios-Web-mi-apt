<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $products = Product::query()
            ->where('is_active', true)
            ->when($filters['category_id'] ?? null, fn ($query, string $categoryId) => $query->where('category_id', $categoryId))
            ->when($filters['q'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 12)
            ->withQueryString();

        return ProductResource::collection($products);
    }

    public function store(Request $request): JsonResponse
    {
        $product = Product::create($this->validatedData($request));

        return (new ProductResource($product))->response()->setStatusCode(201);
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product);
    }

    public function update(Request $request, Product $product): ProductResource
    {
        $product->update($this->validatedData($request, $product));

        return new ProductResource($product->fresh());
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(['message' => 'Producto eliminado correctamente.']);
    }

    private function validatedData(Request $request, ?Product $product = null): array
    {
        $required = $request->isMethod('patch') ? 'sometimes' : 'required';
        $rules = [
            'sku' => [$required, 'string', 'max:40', Rule::unique(Product::class, 'sku')->ignore($product)],
            'name' => [$required, 'string', 'min:2', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => [$required, 'numeric', 'min:0', 'max:999999.99'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'stock' => [$required, 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['sometimes', 'boolean'],
            'category_id' => ['nullable', 'string'],
            'images' => ['sometimes', 'array', 'max:10'],
            'images.*' => ['url', 'max:500'],
            'tags' => ['sometimes', 'array', 'max:20'],
            'tags.*' => ['string', 'max:50'],
            'attributes' => ['sometimes', 'array', 'max:30'],
        ];

        $data = $request->validate($rules);

        if (array_key_exists('sku', $data)) {
            $data['sku'] = strtoupper(trim($data['sku']));
        }

        if (array_key_exists('name', $data)) {
            $data['name'] = trim($data['name']);
        }

        if (array_key_exists('currency', $data)) {
            $data['currency'] = strtoupper($data['currency']);
        }

        if (! $product) {
            $data['description'] ??= null;
            $data['currency'] ??= 'MXN';
            $data['is_active'] ??= true;
            $data['images'] ??= [];
            $data['tags'] ??= [];
            $data['attributes'] ??= [];
        }

        return $data;
    }
}
