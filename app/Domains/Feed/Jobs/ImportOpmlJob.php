<?php

namespace App\Domains\Feed\Jobs;

use Throwable;
use App\Models\Feed;
use App\Models\Category;
use Illuminate\Bus\PendingChain;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Bus;
use Illuminate\Foundation\Bus\Dispatchable;

final class ImportOpmlJob
{
    use Dispatchable;

    /**
     * @param  list<array{name: string, url: string, description: ?string, category: ?string}>  $feeds
     */
    public function __construct(
        private readonly array $feeds,
        private readonly int $duplicates = 0,
        private readonly int $invalid = 0,
        private readonly int $unsupported = 0,
    ) {}

    /** @return array{created: int, existing: int, categories_created: int, duplicates: int, invalid: int, unsupported: int, failures: int} */
    public function handle(): array
    {
        $fetchJobs = [];
        $created = 0;
        $existing = 0;
        $failures = 0;
        $categoriesCreated = [];

        foreach ($this->feeds as $outline) {
            try {
                $result = DB::transaction(function () use ($outline): array {
                    if (Feed::query()->where('url', $outline['url'])->exists()) {
                        return ['created' => false, 'category_created' => false, 'job' => null];
                    }

                    $categoryCreated = false;
                    $categoryId = null;
                    if ($outline['category'] !== null) {
                        $categoryCreated = ! Category::query()->where('name', $outline['category'])->exists();
                        $categoryId = Category::query()->firstOrCreate(['name' => $outline['category']])->id;
                    }

                    $host = parse_url($outline['url'], PHP_URL_HOST) ?: '';
                    $feed = Feed::query()->create([
                        'name' => $outline['name'],
                        'url' => $outline['url'],
                        'description' => $outline['description'],
                        'active' => true,
                        'category_id' => $categoryId,
                        'favicon_url' => $host !== ''
                            ? 'https://www.google.com/s2/favicons?domain='.urlencode($host).'&sz=32'
                            : null,
                    ]);

                    return ['created' => true, 'category_created' => $categoryCreated, 'job' => new FetchFeedJob($feed->id)];
                });

                if (! $result['created']) {
                    $existing++;

                    continue;
                }

                $created++;
                if ($result['category_created'] && $outline['category'] !== null) {
                    $categoriesCreated[$outline['category']] = true;
                }
                $fetchJobs[] = $result['job'];
            } catch (Throwable $exception) {
                report($exception);
                $failures++;
            }
        }

        if ($fetchJobs !== []) {
            /** @var PendingChain $chain */
            $chain = Bus::chain($fetchJobs);
            $chain->dispatch();
        }

        return [
            'created' => $created,
            'existing' => $existing,
            'categories_created' => count($categoriesCreated),
            'duplicates' => $this->duplicates,
            'invalid' => $this->invalid,
            'unsupported' => $this->unsupported,
            'failures' => $failures,
        ];
    }
}
