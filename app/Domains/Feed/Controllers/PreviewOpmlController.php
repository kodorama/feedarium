<?php

namespace App\Domains\Feed\Controllers;

use App\Models\Feed;
use InvalidArgumentException;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use App\Domains\Feed\Support\OpmlParser;
use Illuminate\Validation\ValidationException;
use App\Domains\Feed\Requests\PreviewOpmlRequest;

final class PreviewOpmlController extends Controller
{
    public function __invoke(PreviewOpmlRequest $request, OpmlParser $parser): JsonResponse
    {
        try {
            $parsed = $parser->parse($request->file('file')->get());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        $existingUrls = Feed::query()
            ->whereIn('url', array_column($parsed['feeds'], 'url'))
            ->pluck('url')
            ->mapWithKeys(fn (string $url): array => [$url => true]);

        $feeds = array_map(function (array $feed) use ($existingUrls): array {
            $feed['already_subscribed'] = isset($existingUrls[$feed['url']]);

            return $feed;
        }, $parsed['feeds']);

        return response()->json([
            'feeds' => $feeds,
            'summary' => [
                'feed_count' => count($feeds),
                'already_subscribed' => count(array_filter($feeds, fn (array $feed): bool => $feed['already_subscribed'])),
                'new_feeds' => count(array_filter($feeds, fn (array $feed): bool => ! $feed['already_subscribed'])),
                'duplicates' => $parsed['duplicates'],
                'invalid' => $parsed['invalid'],
                'unsupported' => $parsed['unsupported'],
                'categories' => count(array_unique(array_filter(array_column($parsed['feeds'], 'category')))),
            ],
        ]);
    }
}
