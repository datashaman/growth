<?php

namespace Database\Factories;

use App\Models\DecisionRequestOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DecisionRequestOption>
 */
class DecisionRequestOptionFactory extends Factory
{
    /**
     * Define the model's default state. The caller must supply the parent —
     * `DecisionRequestOption::factory()->for($decisionRequest)`.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'label' => fake()->sentence(3),
            'position' => 0,
        ];
    }
}
