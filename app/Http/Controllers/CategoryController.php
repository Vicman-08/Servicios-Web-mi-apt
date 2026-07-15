<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return $this->paginated($request, publicOnly: true);
    }

    public function adminIndex(Request $request): AnonymousResourceCollection
    {
        return $this->paginated($request, publicOnly: false);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedData($request);
        $data['slug'] = Str::slug($data['slug'] ?? $data['name']);
        $this->ensureUniqueSlug($data['slug']);
        $category = Category::create($data);

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    public function show(Category $category): CategoryResource
    {
        abort_unless($category->is_active, 404);

        return new CategoryResource($category);
    }

    public function adminShow(Category $category): CategoryResource
    {
        return new CategoryResource($category);
    }

    public function update(Request $request, Category $category): CategoryResource
    {
        $data = $this->validatedData($request, $category);

        if (array_key_exists('slug', $data)) {
            $data['slug'] = Str::slug($data['slug']);
            $this->ensureUniqueSlug($data['slug'], $category);
        }

        $category->update($data);

        return new CategoryResource($category->fresh());
    }

    public function destroy(Category $category): JsonResponse
    {
        if (Product::where('category_id', (string) $category->getKey())->exists()) {
            return response()->json([
                'message' => 'No puedes eliminar una categoría que todavía tiene productos.',
            ], 422);
        }

        $category->delete();

        return response()->json(['message' => 'Categoría eliminada correctamente.']);
    }

    private function paginated(Request $request, bool $publicOnly): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $categories = Category::query()
            ->when($publicOnly, fn ($query) => $query->where('is_active', true))
            ->when($filters['q'] ?? null, fn ($query, string $search) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 20)
            ->withQueryString();

        return CategoryResource::collection($categories);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, ?Category $category = null): array
    {
        $required = $request->isMethod('patch') ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$required, 'string', 'min:2', 'max:100'],
            'slug' => ['sometimes', 'required', 'string', 'min:2', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ]) + ($category ? [] : ['description' => null, 'is_active' => true]);
    }

    private function ensureUniqueSlug(string $slug, ?Category $category = null): void
    {
        $existing = Category::where('slug', $slug)->first();

        if ($existing && (! $category || ! $existing->is($category))) {
            throw ValidationException::withMessages([
                'slug' => ['Ya existe una categoría con este identificador.'],
            ]);
        }
    }
}
