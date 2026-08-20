<?php

namespace Database\Factories;

use App\Enums\GroupType;
use App\Enums\GroupVisibility;
use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    /** @var list<array{is: string, en: string}> */
    private const array INTEREST_NAMES = [
        ['is' => 'Gönguhópurinn', 'en' => 'Hiking group'],
        ['is' => 'Ljósmyndaklúbburinn', 'en' => 'Photography club'],
        ['is' => 'Bókaklúbburinn', 'en' => 'Book club'],
        ['is' => 'Hjólahópurinn', 'en' => 'Cycling group'],
        ['is' => 'Prjónahópurinn', 'en' => 'Knitting circle'],
        ['is' => 'Fuglaskoðun', 'en' => 'Birdwatching'],
        ['is' => 'Kaffiklúbburinn', 'en' => 'Coffee club'],
        ['is' => 'Hlaupahópurinn', 'en' => 'Running group'],
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement(self::INTEREST_NAMES);

        return [
            'name' => $name,
            'description' => [
                'is' => 'Óformlegur hópur fyrir áhugasama.',
                'en' => 'An informal group for anyone interested.',
            ],
            'type' => GroupType::Interest,
            'visibility' => GroupVisibility::Open,
        ];
    }

    public function department(): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => ['is' => 'NVS '.fake()->unique()->city(), 'en' => 'NVS '.fake()->unique()->city()],
            'type' => GroupType::Department,
            'visibility' => GroupVisibility::Open,
            'external_organization_id' => (string) fake()->unique()->numberBetween(39163, 39999),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => ['visibility' => GroupVisibility::Closed]);
    }

    public function secret(): static
    {
        return $this->state(fn (array $attributes): array => ['visibility' => GroupVisibility::Secret]);
    }
}
