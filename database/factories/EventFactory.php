<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->randomElement([
            'Starfsmannafundur', 'Vettvangsferð', 'Fræðsluerindi', 'Sumarhátíð', 'Öryggisnámskeið',
        ]).' '.fake()->numberBetween(1, 99);

        $starts = fake()->dateTimeBetween('now', '+2 months');

        return [
            'title' => ['is' => $title, 'en' => $title],
            'description' => ['is' => fake()->sentence(), 'en' => fake()->sentence()],
            'location' => fake()->randomElement(['Reykjavík', 'Akureyri', 'Þingvellir', 'Fjarfundur']),
            'starts_at' => $starts,
            'ends_at' => (clone $starts)->modify('+2 hours'),
            'is_all_day' => false,
            'created_by' => User::factory(),
        ];
    }

    public function allDay(): static
    {
        return $this->state(fn (): array => ['is_all_day' => true, 'ends_at' => null]);
    }

    public function past(): static
    {
        return $this->state(fn (): array => [
            'starts_at' => fake()->dateTimeBetween('-2 months', '-1 day'),
        ]);
    }
}
