<?php

namespace Database\Factories;

use App\Models\McpSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<McpSession>
 */
class McpSessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mcp_session_id' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'role_id' => null,
        ];
    }
}
