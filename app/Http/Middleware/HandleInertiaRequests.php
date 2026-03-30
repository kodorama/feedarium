<?php

namespace App\Http\Middleware;

use App\Models\Feed;
use Inertia\Middleware;
use App\Models\Category;
use Tighten\Ziggy\Ziggy;
use Illuminate\Http\Request;
use Illuminate\Foundation\Inspiring;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
            ],
            'ziggy' => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'appearance' => $request->cookie('appearance', 'system'),
            'sidebarCategories' => $request->user()
                ? Category::query()->orderBy('name')->get(['id', 'name'])
                : [],
            'sidebarFeeds' => $request->user()
                ? Feed::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'category_id', 'favicon_url'])
                : [],
        ];
    }
}
