<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        foreach (range(1, 50) as $number) {
            Product::query()->updateOrCreate(
                ['name' => sprintf('Test Product %02d', $number)],
                [
                    'price' => 5_000 + ($number * 2_500),
                    'quantity' => 1_000 + ($number * 100),
                ],
            );
        }
    }
}
