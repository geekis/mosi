<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'group_id' => null,
            'body' => '<p>'.fake()->paragraph().'</p>',
            'is_official' => false,
            'is_pinned' => false,
            'published_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => ['published_at' => null]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes): array => ['published_at' => now()->addDay()]);
    }

    public function official(): static
    {
        return $this->state(fn (array $attributes): array => ['is_official' => true]);
    }

    public function pinned(): static
    {
        return $this->state(fn (array $attributes): array => ['is_pinned' => true]);
    }

    public function inGroup(Group $group): static
    {
        return $this->state(fn (array $attributes): array => ['group_id' => $group->id]);
    }
}
