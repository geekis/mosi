<?php

namespace Database\Seeders;

use App\Enums\ReactionType;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Realistic feed content: posts (personal and in-group), reactions and comments, so the
 * feed and group pages have something to render and N+1 / pagination bugs surface.
 */
class PostSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()->where('is_active', true)->get();

        // ~180 posts spread over the last month, roughly half in one of the author's groups.
        $users->random(min(120, $users->count()))->each(function (User $author): void {
            foreach (range(1, random_int(1, 3)) as $ignored) {
                $group = random_int(0, 1) === 1 ? $author->groups()->inRandomOrder()->first() : null;

                Post::factory()->create([
                    'user_id' => $author->id,
                    'group_id' => $group?->id,
                    'is_official' => false,
                    'published_at' => now()->subDays(random_int(0, 30))->subMinutes(random_int(0, 1440)),
                ]);
            }
        });

        // Reactions and comments on a slice of posts.
        Post::query()->inRandomOrder()->limit(140)->get()->each(function (Post $post) use ($users): void {
            foreach ($users->random(random_int(0, 12)) as $reactor) {
                Reaction::firstOrCreate(
                    ['reactable_id' => $post->id, 'reactable_type' => $post->getMorphClass(), 'user_id' => $reactor->id],
                    ['type' => fake()->randomElement(ReactionType::cases())->value],
                );
            }

            foreach (range(1, random_int(0, 4)) as $ignored) {
                Comment::factory()->create(['post_id' => $post->id, 'user_id' => $users->random()->id]);
            }
        });
    }
}
