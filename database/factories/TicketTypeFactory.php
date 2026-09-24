<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketType>
 */
class TicketTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => fake()->randomElement(['General Admission', 'VIP', 'Early Bird', 'Student']),
            'price' => fake()->randomFloat(2, 10, 250),
            'quantity' => fake()->numberBetween(20, 500),
        ];
    }
}
