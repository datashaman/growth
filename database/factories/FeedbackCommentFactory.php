<?php

namespace Database\Factories;

use App\Models\FeedbackComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeedbackComment>
 */
class FeedbackCommentFactory extends Factory
{
    /**
     * Define the model's default state. The parent feedback must be supplied
     * by the caller — `FeedbackComment::factory()->for($feedback)->create()`.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'acting_surface' => null,
            'body' => fake()->sentence(),
        ];
    }
}
