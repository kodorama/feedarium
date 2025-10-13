<?php

namespace Database\Factories;

use App\Models\Feed;
use App\Models\News;
use Illuminate\Database\Eloquent\Factories\Factory;

class NewsFactory extends Factory
{
    protected $model = News::class;

    public function definition(): array
    {
        return [
            'feed_id' => Feed::factory(),
            'title' => $this->faker->sentence(),
            'link' => $this->faker->url(),
            'description' => $this->faker->paragraph(),
            'published_at' => $this->faker->dateTime(),
            'author' => $this->faker->name(),
            'guid' => $this->faker->uuid(),
        ];
    }
}
