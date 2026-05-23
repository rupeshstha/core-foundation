<?php

namespace CoreFoundation\Testing;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * BaseFactory
 *
 * Abstract factory base for CoreFoundation models.
 * Extends Laravel's Factory — all native factory capabilities are available:
 * create(), make(), count(), state(), sequence(), recycle(), trashed(), etc.
 *
 * USAGE:
 *
 *   class OrderFactory extends BaseFactory
 *   {
 *       protected $model = Order::class;
 *
 *       public function definition(): array
 *       {
 *           return [
 *               'user_id'    => User::factory(),
 *               'total'      => fake()->randomFloat(2, 10, 999),
 *               'currency'   => 'USD',
 *               'status'     => 'pending',
 *               'placed_at'  => now(),
 *           ];
 *       }
 *
 *       // Named states — fluent, chainable
 *       public function completed(): static
 *       {
 *           return $this->state(['status' => 'completed']);
 *       }
 *
 *       public function cancelled(): static
 *       {
 *           return $this->state(['status' => 'cancelled']);
 *       }
 *   }
 *
 * IN TESTS (Pest):
 *
 *   // Create persisted records
 *   $order = Order::factory()->create();
 *   $orders = Order::factory()->count(5)->completed()->create();
 *
 *   // Make without persisting — for service/unit tests
 *   $attributes = Order::factory()->make()->toArray();
 *
 *   // For request validation testing — get validated-style array
 *   $payload = Order::factory()->definition();
 *
 * MODEL BINDING:
 *
 * Wire the factory to the model via HasFactory on the model:
 *
 *   class Order extends BaseModel
 *   {
 *       use HasFactory;
 *
 *       protected static function newFactory(): OrderFactory
 *       {
 *           return OrderFactory::new();
 *       }
 *   }
 *
 * @template TModel of \CoreFoundation\Entities\BaseModel
 *
 * @extends  Factory<TModel>
 */
abstract class BaseFactory extends Factory
{
    /**
     * Define the model's default state.
     * Return a plain array — Laravel handles Eloquent relation closures,
     * nested factories, sequences, and fake() calls automatically.
     *
     *   public function definition(): array
     *   {
     *       return [
     *           'name'  => fake()->name(),
     *           'email' => fake()->unique()->safeEmail(),
     *       ];
     *   }
     */
    abstract public function definition(): array;
}
