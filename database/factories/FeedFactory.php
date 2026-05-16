<?php

namespace Database\Factories;

use App\Models\Feed;
use Illuminate\Database\Eloquent\Factories\Factory;

class FeedFactory extends Factory
{
    protected $model = Feed::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'url' => $this->faker->url(),
            'description' => $this->faker->sentence(),
            'active' => true,
            'disable_full_article_scraping' => false,
            'hide_image_in_detail' => false,
        ];
    }
}
