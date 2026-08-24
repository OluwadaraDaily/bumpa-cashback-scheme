<?php

namespace Tests\Feature;

use App\Models\Product;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_seeder_creates_fifty_products_with_stock(): void
    {
        $this->seed(ProductSeeder::class);

        $this->assertDatabaseCount('products', 50);
        $this->assertGreaterThan(0, Product::query()->where('quantity', '>', 0)->count());
        $this->assertSame(7_500, Product::query()->where('name', 'Test Product 01')->value('price'));
        $this->assertSame(1_100, Product::query()->where('name', 'Test Product 01')->value('quantity'));
    }

    public function test_product_seeder_is_safe_to_run_more_than_once(): void
    {
        $this->seed(ProductSeeder::class);
        $this->seed(ProductSeeder::class);

        $this->assertDatabaseCount('products', 50);
    }
}
