<?php

declare(strict_types=1);

namespace Workbench\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Workbench\App\Models\DemoOrder;

/**
 * @extends Factory<DemoOrder>
 */
final class DemoOrderFactory extends Factory
{
    /**
     * @var class-string<DemoOrder>
     */
    protected $model = DemoOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = fake()->randomElement(['pending', 'paid', 'shipped', 'cancelled']);

        return [
            'number' => 'ORD-'.fake()->unique()->numerify('######'),
            'customer' => fake()->name(),
            'status' => $status,
            'channel' => fake()->randomElement(['store', 'web', 'phone']),
            'total' => fake()->randomFloat(2, 5, 500),
            'items_count' => fake()->numberBetween(1, 12),
            'paid' => in_array($status, ['paid', 'shipped'], true),
            'ordered_at' => fake()->dateTimeBetween('-12 months'),
        ];
    }
}
