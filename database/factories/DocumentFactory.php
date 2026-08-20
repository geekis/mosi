<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->randomElement(['Starfsmannahandbók', 'Öryggisreglur', 'Ferðareglur', 'Siðareglur']).' '.fake()->year();

        return [
            'title' => ['is' => $title, 'en' => $title],
            'description' => ['is' => fake()->sentence(), 'en' => fake()->sentence()],
            'category' => fake()->randomElement(['HR', 'Öryggi', 'Stefnur']),
            'is_published' => true,
            'uploaded_by' => User::factory(),
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn (): array => ['is_published' => false]);
    }
}
