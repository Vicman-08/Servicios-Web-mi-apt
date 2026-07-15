<?php

namespace Tests\Feature;

use App\Models\AiInteraction;
use App\Models\Cart;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MongoCrudTest extends TestCase
{
    use DatabaseMigrations;

    public function test_login_issues_a_mongodb_token_that_authenticates_requests(): void
    {
        $user = $this->user('buyer');

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk()
            ->assertJsonPath('data.expires_in', 300)
            ->assertJsonStructure(['data' => ['expires_at', 'token', 'user']]);

        $this->withToken($login->json('data.token'))
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);

        $this->travel(6)->minutes();
        $this->app['auth']->forgetGuards();

        $this->withToken($login->json('data.token'))
            ->getJson('/api/v1/me')
            ->assertUnauthorized();
    }

    public function test_public_registration_always_creates_a_buyer_account(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Registro Público',
            'email' => 'registro@subarg.test',
            'password' => 'password123',
            'role' => 'admin',
        ]);

        $response->assertCreated()->assertJsonPath('data.role', 'buyer');
        $this->assertSame('buyer', User::where('email', 'registro@subarg.test')->firstOrFail()->role);
    }

    public function test_admin_can_use_the_complete_product_crud(): void
    {
        Sanctum::actingAs($this->user('admin'));

        $created = $this->postJson('/api/v1/admin/products', [
            'sku' => 'CRUD-001',
            'name' => 'Producto CRUD',
            'description' => 'Creado durante la prueba.',
            'price' => 125.50,
            'stock' => 10,
            'currency' => 'MXN',
            'is_active' => true,
        ])->assertCreated();

        $id = $created->json('data.id');

        $this->getJson("/api/v1/products/{$id}")
            ->assertOk()
            ->assertJsonPath('data.sku', 'CRUD-001');

        $this->patchJson("/api/v1/admin/products/{$id}", [
            'name' => 'Producto actualizado',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Producto actualizado')
            ->assertJsonPath('data.sku', 'CRUD-001')
            ->assertJsonPath('data.stock', 10);

        $this->deleteJson("/api/v1/admin/products/{$id}")->assertOk();
        $this->assertNull(Product::find($id));
    }

    public function test_observer_can_browse_but_cannot_modify_or_buy(): void
    {
        Product::create([
            'sku' => 'PUBLIC-001',
            'name' => 'Producto público',
            'description' => null,
            'price' => '10.00',
            'currency' => 'MXN',
            'stock' => 1,
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure(['data', 'links', 'meta']);

        $this->postJson('/api/v1/admin/products', [
            'sku' => 'NO-ACCESS',
            'name' => 'No autorizado',
            'price' => 10,
            'stock' => 1,
        ])->assertUnauthorized();

        $this->postJson('/api/v1/cart/items', [
            'product_id' => (string) Product::firstOrFail()->getKey(),
            'quantity' => 1,
        ])->assertUnauthorized();

        $this->postJson('/api/v1/checkout')->assertUnauthorized();
    }

    public function test_admin_can_use_the_user_crud(): void
    {
        Sanctum::actingAs($this->user('admin'));

        $created = $this->postJson('/api/v1/admin/users', [
            'name' => 'Usuario administrado',
            'email' => 'managed@feature.test',
            'password' => 'password123',
            'role' => 'buyer',
        ])->assertCreated();

        $id = $created->json('data.id');

        $this->patchJson("/api/v1/admin/users/{$id}", [
            'role' => 'buyer',
            'status' => 'active',
        ])->assertOk()->assertJsonPath('data.role', 'buyer');

        $this->deleteJson("/api/v1/admin/users/{$id}")->assertOk();
        $this->assertNull(User::find($id));
    }

    public function test_buyer_can_purchase_and_cancel_without_negative_stock(): void
    {
        $buyer = $this->user('buyer');
        $product = Product::create([
            'sku' => 'BUY-001',
            'name' => 'Producto comprable',
            'description' => null,
            'price' => '50.00',
            'currency' => 'MXN',
            'stock' => 3,
            'is_active' => true,
        ]);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/cart/items', [
            'product_id' => (string) $product->getKey(),
            'quantity' => 2,
        ])->assertOk();

        $order = $this->postJson('/api/v1/checkout')->assertCreated();

        $this->assertSame(1, $product->fresh()->stock);

        $this->postJson('/api/v1/cart/items', [
            'product_id' => (string) $product->getKey(),
            'quantity' => 2,
        ])->assertUnprocessable();

        $this->deleteJson('/api/v1/orders/'.$order->json('data.id'))->assertOk();
        $this->assertSame(3, $product->fresh()->stock);
    }

    public function test_buyer_can_manage_cart_and_see_calculated_totals(): void
    {
        $buyer = $this->user('buyer');
        $first = Product::create([
            'sku' => 'CART-001',
            'name' => 'Primer producto',
            'description' => null,
            'price' => '25.50',
            'currency' => 'MXN',
            'stock' => 10,
            'is_active' => true,
        ]);
        $second = Product::create([
            'sku' => 'CART-002',
            'name' => 'Segundo producto',
            'description' => null,
            'price' => '10.00',
            'currency' => 'MXN',
            'stock' => 5,
            'is_active' => true,
        ]);

        Sanctum::actingAs($buyer);

        $this->getJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonPath('data.total', '0.00');

        $this->postJson('/api/v1/cart/items', [
            'product_id' => (string) $first->getKey(),
            'quantity' => 1,
        ])->assertOk();

        $this->postJson('/api/v1/cart/items', [
            'product_id' => (string) $first->getKey(),
            'quantity' => 2,
        ])->assertOk()
            ->assertJsonPath('data.item_count', 3)
            ->assertJsonPath('data.total', '76.50');

        $this->postJson('/api/v1/cart/items', [
            'product_id' => (string) $second->getKey(),
            'quantity' => 2,
        ])->assertOk()
            ->assertJsonPath('data.item_count', 5)
            ->assertJsonPath('data.total', '96.50');

        $this->patchJson('/api/v1/cart/items/'.$first->getKey(), [
            'quantity' => 2,
        ])->assertOk()
            ->assertJsonPath('data.item_count', 4)
            ->assertJsonPath('data.total', '71.00');

        $this->deleteJson('/api/v1/cart/items/'.$second->getKey())
            ->assertOk()
            ->assertJsonCount(1, 'data.items');

        $this->deleteJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonCount(0, 'data.items')
            ->assertJsonPath('data.total', '0.00');
    }

    public function test_checkout_creates_one_multi_product_order_and_cancellation_restores_stock(): void
    {
        $buyer = $this->user('buyer');
        $first = Product::create([
            'sku' => 'CHECKOUT-001',
            'name' => 'Producto A',
            'description' => null,
            'price' => '30.00',
            'currency' => 'MXN',
            'stock' => 5,
            'is_active' => true,
        ]);
        $second = Product::create([
            'sku' => 'CHECKOUT-002',
            'name' => 'Producto B',
            'description' => null,
            'price' => '12.50',
            'currency' => 'MXN',
            'stock' => 4,
            'is_active' => true,
        ]);

        Sanctum::actingAs($buyer);
        $this->postJson('/api/v1/cart/items', ['product_id' => (string) $first->getKey(), 'quantity' => 2])->assertOk();
        $this->postJson('/api/v1/cart/items', ['product_id' => (string) $second->getKey(), 'quantity' => 3])->assertOk();

        $order = $this->postJson('/api/v1/checkout')
            ->assertCreated()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.total', '97.50')
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertSame(3, $first->fresh()->stock);
        $this->assertSame(1, $second->fresh()->stock);
        $this->assertSame(2, InventoryMovement::where('type', 'sale')->count());
        $this->getJson('/api/v1/cart')->assertOk()->assertJsonCount(0, 'data.items');

        $this->deleteJson('/api/v1/orders/'.$order->json('data.id'))->assertOk();
        $this->assertSame(5, $first->fresh()->stock);
        $this->assertSame(4, $second->fresh()->stock);
        $this->assertSame(2, InventoryMovement::where('type', 'cancellation')->count());
    }

    public function test_checkout_rolls_back_previous_stock_changes_when_an_item_is_insufficient(): void
    {
        $buyer = $this->user('buyer');
        $first = Product::create([
            'sku' => 'ROLLBACK-001',
            'name' => 'Producto con inventario',
            'description' => null,
            'price' => '20.00',
            'currency' => 'MXN',
            'stock' => 5,
            'is_active' => true,
        ]);
        $second = Product::create([
            'sku' => 'ROLLBACK-002',
            'name' => 'Producto agotado después',
            'description' => null,
            'price' => '15.00',
            'currency' => 'MXN',
            'stock' => 1,
            'is_active' => true,
        ]);

        Sanctum::actingAs($buyer);
        $this->postJson('/api/v1/cart/items', ['product_id' => (string) $first->getKey(), 'quantity' => 2])->assertOk();
        $this->postJson('/api/v1/cart/items', ['product_id' => (string) $second->getKey(), 'quantity' => 1])->assertOk();
        $second->update(['stock' => 0]);

        $this->postJson('/api/v1/checkout')->assertUnprocessable();

        $this->assertSame(5, $first->fresh()->stock);
        $this->assertSame(0, $second->fresh()->stock);
        $this->assertSame(0, Order::count());
        $this->assertSame(0, InventoryMovement::count());
        $this->getJson('/api/v1/cart')->assertOk()->assertJsonCount(2, 'data.items');
    }

    public function test_public_catalog_is_paginated_and_filters_inactive_products(): void
    {
        foreach (range(1, 3) as $number) {
            Product::create([
                'sku' => "PAGE-00{$number}",
                'name' => "Producto {$number}",
                'description' => null,
                'price' => '10.00',
                'currency' => 'MXN',
                'stock' => 1,
                'is_active' => $number !== 3,
            ]);
        }

        $this->getJson('/api/v1/products?per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 1);
    }

    public function test_new_store_collections_accept_the_planned_documents(): void
    {
        $buyer = $this->user('buyer');
        $category = Category::create([
            'name' => 'Accesorios',
            'slug' => 'accesorios',
            'description' => null,
            'is_active' => true,
        ]);

        $cart = Cart::create([
            'user_id' => (string) $buyer->getKey(),
            'items' => [],
            'currency' => 'MXN',
        ]);

        $interaction = AiInteraction::create([
            'user_id' => (string) $buyer->getKey(),
            'query' => 'Necesito un teclado para oficina',
            'response' => null,
            'provider' => 'pending',
            'model' => 'pending',
            'status' => 'success',
            'duration_ms' => 0,
            'metadata' => ['stage' => 2],
        ]);

        $this->assertSame('accesorios', $category->slug);
        $this->assertSame([], $cart->items);
        $this->assertSame(2, $interaction->metadata['stage']);
    }

    public function test_buyer_can_update_profile_without_changing_role(): void
    {
        $buyer = $this->user('buyer');
        Sanctum::actingAs($buyer);

        $this->patchJson('/api/v1/me', [
            'name' => 'Cliente actualizado',
            'phone' => '5551234567',
            'role' => 'admin',
            'addresses' => [[
                'label' => 'Casa',
                'recipient' => 'Cliente actualizado',
                'line1' => 'Calle Uno 123',
                'line2' => null,
                'city' => 'Ciudad de México',
                'state' => 'CDMX',
                'postal_code' => '01000',
                'country' => 'MX',
            ]],
        ])->assertOk()
            ->assertJsonPath('data.name', 'Cliente actualizado')
            ->assertJsonPath('data.phone', '5551234567')
            ->assertJsonPath('data.role', 'buyer')
            ->assertJsonPath('data.addresses.0.label', 'Casa');
    }

    public function test_admin_can_manage_categories_without_deleting_one_in_use(): void
    {
        Sanctum::actingAs($this->user('admin'));

        $created = $this->postJson('/api/v1/admin/categories', [
            'name' => 'Oficina',
            'description' => 'Productos para oficina.',
        ])->assertCreated()
            ->assertJsonPath('data.slug', 'oficina');

        $categoryId = $created->json('data.id');

        $this->postJson('/api/v1/admin/categories', [
            'name' => 'Oficina',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('slug');

        $this->patchJson("/api/v1/admin/categories/{$categoryId}", [
            'description' => 'Accesorios para trabajar.',
        ])->assertOk()
            ->assertJsonPath('data.description', 'Accesorios para trabajar.');

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonPath('data.0.id', $categoryId);

        $product = Product::create([
            'sku' => 'CAT-001',
            'name' => 'Producto categorizado',
            'description' => null,
            'price' => '25.00',
            'currency' => 'MXN',
            'stock' => 2,
            'is_active' => true,
            'category_id' => $categoryId,
        ]);

        $this->deleteJson("/api/v1/admin/categories/{$categoryId}")
            ->assertUnprocessable();

        $product->delete();
        $this->deleteJson("/api/v1/admin/categories/{$categoryId}")->assertOk();
    }

    public function test_admin_catalog_includes_inactive_products_but_public_catalog_does_not(): void
    {
        Product::create([
            'sku' => 'HIDDEN-001',
            'name' => 'Producto oculto',
            'description' => null,
            'price' => '50.00',
            'currency' => 'MXN',
            'stock' => 1,
            'is_active' => false,
        ]);

        $this->getJson('/api/v1/products')->assertOk()->assertJsonCount(0, 'data');

        Sanctum::actingAs($this->user('admin'));
        $this->getJson('/api/v1/admin/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_active', false);
    }

    public function test_admin_can_adjust_inventory_without_creating_negative_stock(): void
    {
        $product = Product::create([
            'sku' => 'STOCK-001',
            'name' => 'Producto con ajustes',
            'description' => null,
            'price' => '80.00',
            'currency' => 'MXN',
            'stock' => 5,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->user('admin'));

        $this->postJson('/api/v1/admin/inventory-adjustments', [
            'product_id' => (string) $product->getKey(),
            'quantity_delta' => 4,
            'reason' => 'Recepción de mercancía',
        ])->assertCreated()
            ->assertJsonPath('data.product.stock', 9)
            ->assertJsonPath('data.movement.type', 'restock');

        $this->postJson('/api/v1/admin/inventory-adjustments', [
            'product_id' => (string) $product->getKey(),
            'quantity_delta' => -3,
            'reason' => 'Producto dañado',
        ])->assertCreated()
            ->assertJsonPath('data.product.stock', 6)
            ->assertJsonPath('data.movement.type', 'adjustment');

        $this->postJson('/api/v1/admin/inventory-adjustments', [
            'product_id' => (string) $product->getKey(),
            'quantity_delta' => -7,
            'reason' => 'Ajuste imposible',
        ])->assertUnprocessable();

        $this->getJson('/api/v1/admin/inventory-movements')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_can_advance_order_status_and_view_dashboard(): void
    {
        $buyer = $this->user('buyer');
        $product = Product::create([
            'sku' => 'ADMIN-ORDER-001',
            'name' => 'Producto para dashboard',
            'description' => null,
            'price' => '100.00',
            'currency' => 'MXN',
            'stock' => 2,
            'is_active' => true,
        ]);

        Sanctum::actingAs($buyer);
        $this->postJson('/api/v1/cart/items', [
            'product_id' => (string) $product->getKey(),
            'quantity' => 1,
        ])->assertOk();
        $order = $this->postJson('/api/v1/checkout')->assertCreated();

        $this->getJson('/api/v1/admin/dashboard')->assertForbidden();

        Sanctum::actingAs($this->user('admin'));
        $orderId = $order->json('data.id');

        $this->patchJson("/api/v1/admin/orders/{$orderId}/status", [
            'status' => 'shipped',
        ])->assertOk()
            ->assertJsonPath('data.status', 'shipped');

        $this->getJson('/api/v1/admin/orders?status=shipped')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.orders.total', 1)
            ->assertJsonPath('data.orders.revenue', '100.00')
            ->assertJsonPath('data.products.total', 1);
    }

    public function test_public_ai_route_consumes_openai_and_returns_only_catalog_products(): void
    {
        $product = Product::create([
            'sku' => 'AI-001',
            'name' => 'Teclado recomendado',
            'description' => 'Teclado cómodo para oficina.',
            'price' => '750.00',
            'currency' => 'MXN',
            'stock' => 4,
            'is_active' => true,
            'tags' => ['oficina'],
        ]);
        config()->set('services.openai.key', 'test-openai-key');
        config()->set('services.openai.model', 'gpt-5.6-luna');
        config()->set('services.openai.base_url', 'https://api.openai.com/v1');

        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'id' => 'resp_test_123',
                'model' => 'gpt-5.6-luna-2026-07-01',
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode([
                            'answer' => 'Este teclado es una buena opción para trabajar.',
                            'recommended_product_ids' => [(string) $product->getKey(), 'id-inventado'],
                        ]),
                    ]],
                ]],
                'usage' => ['input_tokens' => 100, 'output_tokens' => 30, 'total_tokens' => 130],
            ]),
        ]);

        $this->postJson('/api/v1/ai/recommendations', [
            'query' => 'Necesito un teclado para trabajar por menos de mil pesos',
        ])->assertOk()
            ->assertJsonPath('data.provider', 'openai')
            ->assertJsonPath('data.model', 'gpt-5.6-luna-2026-07-01')
            ->assertJsonCount(1, 'data.recommendations')
            ->assertJsonPath('data.recommendations.0.id', (string) $product->getKey());

        $interaction = AiInteraction::firstOrFail();
        $this->assertSame('success', $interaction->status);
        $this->assertSame('openai', $interaction->provider);
        $this->assertNull($interaction->user_id);
        $this->assertSame([(string) $product->getKey()], $interaction->metadata['recommended_product_ids']);

        Http::assertSent(function ($request) use ($product): bool {
            return $request->url() === 'https://api.openai.com/v1/responses'
                && $request->hasHeader('Authorization', 'Bearer test-openai-key')
                && $request['store'] === false
                && $request['text']['format']['type'] === 'json_schema'
                && str_contains($request['input'], (string) $product->getKey());
        });
    }

    public function test_ai_route_reports_missing_configuration_and_records_the_error(): void
    {
        Product::create([
            'sku' => 'AI-CONFIG-001',
            'name' => 'Producto para IA',
            'description' => null,
            'price' => '100.00',
            'currency' => 'MXN',
            'stock' => 1,
            'is_active' => true,
        ]);
        config()->set('services.openai.key', null);

        $this->postJson('/api/v1/ai/recommendations', [
            'query' => 'Recomiéndame algo',
        ])->assertStatus(503)
            ->assertJsonPath('message', 'El servicio de IA no está configurado. Agrega OPENAI_API_KEY en el archivo .env del servidor.');

        $this->assertSame('error', AiInteraction::firstOrFail()->status);
        Http::assertNothingSent();
    }

    public function test_only_admin_can_review_ai_interaction_history(): void
    {
        AiInteraction::create([
            'user_id' => null,
            'query' => 'Consulta guardada',
            'response' => 'Respuesta guardada',
            'provider' => 'openai',
            'model' => 'gpt-5.6-luna',
            'status' => 'success',
            'duration_ms' => 125,
            'metadata' => ['external_id' => 'resp_test'],
        ]);

        Sanctum::actingAs($this->user('buyer'));
        $this->getJson('/api/v1/admin/ai-interactions')->assertForbidden();

        Sanctum::actingAs($this->user('admin'));
        $this->getJson('/api/v1/admin/ai-interactions?status=success')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.provider', 'openai')
            ->assertJsonPath('data.0.duration_ms', 125);
    }

    public function test_administration_has_a_separate_web_console(): void
    {
        $this->withoutVite()
            ->get('/admin')
            ->assertOk()
            ->assertSee('Acceso restringido')
            ->assertSee('Administración')
            ->assertSee('Productos')
            ->assertSee('Categorías')
            ->assertSee('Usuarios')
            ->assertSee('Órdenes')
            ->assertSee('Inventario')
            ->assertSee('Actividad IA');
    }

    public function test_legacy_unversioned_api_is_no_longer_exposed(): void
    {
        $this->getJson('/api/products')->assertNotFound();
    }

    private function user(string $role): User
    {
        return User::create([
            'name' => ucfirst($role).' de prueba',
            'email' => $role.'@feature.test',
            'password' => 'password123',
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
