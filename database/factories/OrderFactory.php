<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => Order::STATUS_COMPLETED,
            'total' => 10_000,
            'idempotency_key' => fake()->uuid(),
            'request_hash' => hash('sha256', fake()->uuid()),
        ];
    }
}
