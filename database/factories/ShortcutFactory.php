<?php

namespace Database\Factories;

use App\Models\Shortcut;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shortcut>
 */
class ShortcutFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'label' => $this->faker->unique()->company(),
            'url' => $this->faker->url(),
            'description' => $this->faker->optional()->sentence(4),
            'icon' => $this->faker->optional()->randomElement(Shortcut::ICONS),
            'category' => $this->faker->optional()->randomElement(['Tools', 'HR', 'IT']),
            'opens_in_new_tab' => true,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * A shortcut that is switched off and must not appear in the mega menu.
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
