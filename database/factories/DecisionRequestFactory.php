<?php

namespace Database\Factories;

use App\Models\DecisionRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DecisionRequest>
 */
class DecisionRequestFactory extends Factory
{
    /**
     * Define the model's default state. The caller must supply `project_id`
     * and `target_role_id` — those models have no factory of their own.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requester_user_id' => User::factory(),
            'question' => rtrim(fake()->sentence(), '.').'?',
            'status' => 'open',
        ];
    }
}
