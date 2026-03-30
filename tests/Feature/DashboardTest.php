<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('guests visiting / are redirected to the login page', function () {
    User::factory()->create(); // ensure at least one user exists so no register-admin redirect

    $this->get('/')->assertRedirect('/login');
});

test('authenticated users visiting / are redirected to feeds', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get('/')->assertRedirect(route('feeds.index'));
});

test('authenticated users visiting /feeds receive the correct Inertia component and sidebar props', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->withoutVite()
        ->get('/feeds')
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('feeds/Index')
            ->has('sidebarCategories')
            ->has('sidebarFeeds')
        );
});

test('authenticated users visiting /saved receive the correct Inertia component and sidebar props', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->withoutVite()
        ->get('/saved')
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('feeds/Saved')
            ->has('sidebarCategories')
            ->has('sidebarFeeds')
        );
});
