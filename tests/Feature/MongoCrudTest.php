<?php

namespace Tests\Feature;

use App\Models\AiInteraction;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
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

        $this->postJson('/api/v1/orders', [
            'product_id' => (string) Product::firstOrFail()->getKey(),
            'quantity' => 1,
        ])->assertUnauthorized();
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

        $order = $this->postJson('/api/v1/orders', [
            'product_id' => (string) $product->getKey(),
            'quantity' => 2,
        ])->assertCreated();

        $this->assertSame(1, $product->fresh()->stock);

        $this->postJson('/api/v1/orders', [
            'product_id' => (string) $product->getKey(),
            'quantity' => 2,
        ])->assertUnprocessable();

        $this->deleteJson('/api/v1/orders/'.$order->json('data.id'))->assertOk();
        $this->assertSame(3, $product->fresh()->stock);
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
