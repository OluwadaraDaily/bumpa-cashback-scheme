<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_can_be_listed_publicly(): void
    {
        Product::factory()->create([
            'name' => 'Coffee beans',
            'price' => 25_000,
            'quantity' => 10,
        ]);

        $this->getJson('/products')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Coffee beans')
            ->assertJsonPath('data.0.price', 25_000)
            ->assertJsonPath('data.0.quantity', 10);
    }

    public function test_a_product_can_be_viewed_publicly(): void
    {
        $product = Product::factory()->create();

        $this->getJson("/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.name', $product->name);
    }

    public function test_only_an_admin_can_create_a_product(): void
    {
        $user = User::factory()->create();

        $this->postJson('/products', [
            'name' => 'Coffee beans',
            'price' => 25_000,
            'quantity' => 10,
        ])->assertUnauthorized();

        $this->actingAs($user, 'sanctum')
            ->postJson('/products', [
                'name' => 'Coffee beans',
                'price' => 25_000,
                'quantity' => 10,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('products', 0);
    }

    public function test_an_admin_can_create_a_product(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/products', [
                'name' => 'Coffee beans',
                'price' => 25_000,
                'quantity' => 10,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Coffee beans')
            ->assertJsonPath('data.price', 25_000)
            ->assertJsonPath('data.quantity', 10);

        $this->assertDatabaseHas('products', [
            'name' => 'Coffee beans',
            'price' => 25_000,
            'quantity' => 10,
        ]);
    }

    public function test_product_creation_validates_price_and_quantity(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/products', [
                'name' => 'Coffee beans',
                'price' => 0,
                'quantity' => -1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['price', 'quantity']);
    }
}
