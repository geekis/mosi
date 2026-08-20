<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\GroupVisibility;
use App\Enums\RsvpStatus;
use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Additive: seeds a spread of upcoming events (org-wide and in a few open groups) with
 * realistic RSVPs, without touching existing users. Safe to run on a populated database.
 */
class EventSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()->where('is_active', true)->inRandomOrder()->limit(60)->get();

        if ($users->isEmpty()) {
            return;
        }

        for ($i = 0; $i < 10; $i++) {
            $this->addRsvps(Event::factory()->create(['created_by' => $users->random()->id]), $users);
        }

        Group::query()
            ->where('visibility', '!=', GroupVisibility::Secret->value)
            ->inRandomOrder()
            ->limit(5)
            ->get()
            ->each(function (Group $group): void {
                $member = $group->members()->first();

                if ($member === null) {
                    return;
                }

                $this->addRsvps(
                    Event::factory()->create(['group_id' => $group->id, 'created_by' => $member->id]),
                    $group->members()->get(),
                );
            });
    }

    /**
     * @param  Collection<int, User>  $pool
     */
    private function addRsvps(Event $event, $pool): void
    {
        if ($pool->isEmpty()) {
            return;
        }

        $rows = $pool->random(min($pool->count(), random_int(3, 12)))
            ->mapWithKeys(fn (User $user): array => [
                $user->id => ['status' => fake()->randomElement(RsvpStatus::cases())->value],
            ])
            ->all();

        $event->attendees()->syncWithoutDetaching($rows);
    }
}
