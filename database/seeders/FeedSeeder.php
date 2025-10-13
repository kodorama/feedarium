<?php

namespace Database\Seeders;

use App\Models\Feed;
use Illuminate\Database\Seeder;

class FeedSeeder extends Seeder
{
    public function run(): void
    {
        Feed::factory()->count(10)->create();
    }
}
