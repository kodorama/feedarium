<?php

describe('Category and News CRUD', function () {
    beforeEach(function () {
        \App\Models\User::factory()->create(['is_admin' => true]);
        $this->actingAs(\App\Models\User::first());
    });

    it('creates a category', function () {
        $data = [
            'name' => 'Tech',
            'description' => 'Technology news',
        ];
        $response = $this->postJson('/api/categories', $data);
        $response->assertCreated();
        $this->assertDatabaseHas('categories', [
            'name' => 'Tech',
        ]);
    });

    it('updates a category', function () {
        $category = \App\Models\Category::factory()->create();
        $data = [
            'name' => 'Updated',
            'description' => 'Updated desc',
        ];
        $response = $this->putJson("/api/categories/{$category->id}", $data);
        $response->assertOk();
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Updated',
        ]);
    });

    it('deletes a category', function () {
        $category = \App\Models\Category::factory()->create();
        $response = $this->deleteJson("/api/categories/{$category->id}");
        $response->assertOk();
        $this->assertDatabaseMissing('categories', [
            'id' => $category->id,
        ]);
    });

    it('creates news for a feed', function () {
        $feed = \App\Models\Feed::factory()->create();
        $data = [
            'feed_id' => $feed->id,
            'title' => 'Breaking News',
            'link' => 'https://example.com/news',
            'description' => 'Details...',
            'published_at' => now()->toDateTimeString(),
            'author' => 'Reporter',
            'guid' => 'unique-guid',
        ];
        $response = $this->postJson('/api/news', $data);
        $response->assertCreated();
        $this->assertDatabaseHas('news', [
            'title' => 'Breaking News',
            'feed_id' => $feed->id,
        ]);
    });

    it('assigns a feed to a category', function () {
        $category = \App\Models\Category::factory()->create();
        $feed = \App\Models\Feed::factory()->create(['category_id' => $category->id]);
        $this->assertEquals($category->id, $feed->category->id);
    });
});
