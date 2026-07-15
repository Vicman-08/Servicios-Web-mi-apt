<?php

namespace Tests\Feature;

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

        $login = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk()
            ->assertJsonPath('expires_in', 300)
            ->assertJsonStructure(['expires_at']);

        $this->withToken($login->json('token'))
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('email', $user->email);

        $this->travel(6)->minutes();
        $this->app['auth']->forgetGuards();

        $this->withToken($login->json('token'))
            ->getJson('/api/me')
            ->assertUnauthorized();
    }

    public function test_public_registration_always_creates_a_buyer_account(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Registro Público',
            'email' => 'registro@subarg.test',
            'password' => 'password123',
            'role' => 'admin',
        ]);

        $response->assertCreated()->assertJsonPath('role', 'buyer');
        $this->assertSame('buyer', User::where('email', 'registro@subarg.test')->firstOrFail()->role);
    }

    public function test_admin_can_use_the_complete_product_crud(): void
    {
        Sanctum::actingAs($this->user('admin'));

        $created = $this->postJson('/api/products', [
            'sku' => 'CRUD-001',
            'name' => 'Producto CRUD',
            'description' => 'Creado durante la prueba.',
            'price' => 125.50,
            'stock' => 10,
            'currency' => 'MXN',
            'is_active' => true,
        ])->assertCreated();

        $id = $created->json('id');

        $this->getJson("/api/products/{$id}")
            ->assertOk()
            ->assertJsonPath('sku', 'CRUD-001');

        $this->patchJson("/api/products/{$id}", [
            'sku' => 'CRUD-001',
            'name' => 'Producto actualizado',
            'description' => 'Actualizado durante la prueba.',
            'price' => 199.90,
            'stock' => 7,
            'currency' => 'MXN',
            'is_active' => true,
        ])->assertOk()->assertJsonPath('stock', 7);

        $this->deleteJson("/api/products/{$id}")->assertOk();
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

        $this->getJson('/api/products')->assertOk()->assertJsonCount(1);

        $this->postJson('/api/products', [
            'sku' => 'NO-ACCESS',
            'name' => 'No autorizado',
            'price' => 10,
            'stock' => 1,
        ])->assertUnauthorized();

        $this->postJson('/api/orders', [
            'product_id' => (string) Product::firstOrFail()->getKey(),
            'quantity' => 1,
        ])->assertUnauthorized();
    }

    public function test_admin_can_use_the_user_crud(): void
    {
        Sanctum::actingAs($this->user('admin'));

        $created = $this->postJson('/api/users', [
            'name' => 'Usuario administrado',
            'email' => 'managed@feature.test',
            'password' => 'password123',
            'role' => 'buyer',
        ])->assertCreated();

        $id = $created->json('id');

        $this->patchJson("/api/users/{$id}", [
            'role' => 'buyer',
            'status' => 'active',
        ])->assertOk()->assertJsonPath('role', 'buyer');

        $this->deleteJson("/api/users/{$id}")->assertOk();
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

        $order = $this->postJson('/api/orders', [
            'product_id' => (string) $product->getKey(),
            'quantity' => 2,
        ])->assertCreated();

        $this->assertSame(1, $product->fresh()->stock);

        $this->postJson('/api/orders', [
            'product_id' => (string) $product->getKey(),
            'quantity' => 2,
        ])->assertUnprocessable();

        $this->deleteJson('/api/orders/'.$order->json('id'))->assertOk();
        $this->assertSame(3, $product->fresh()->stock);
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
