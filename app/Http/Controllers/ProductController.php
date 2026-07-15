<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Product::orderBy('created_at', 'desc')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $product = Product::create($this->validatedData($request));

        return response()->json($product, 201);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json($product);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $product->update($this->validatedData($request, $product));

        return response()->json($product->fresh());
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(['message' => 'Producto eliminado correctamente.']);
    }

    private function validatedData(Request $request, ?Product $product = null): array
    {
        $rules = [
            'sku' => ['required', 'string', 'max:40', Rule::unique(Product::class, 'sku')->ignore($product)],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'stock' => ['required', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['sometimes', 'boolean'],
        ];

        $data = $request->validate($rules);

        return [
            ...$data,
            'sku' => strtoupper(trim($data['sku'])),
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
            'currency' => strtoupper($data['currency'] ?? 'MXN'),
            'is_active' => $data['is_active'] ?? true,
        ];
    }
}
