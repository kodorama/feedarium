<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property string $name
 * @property string $url
 * @property string|null $description
 * @property bool $active
 * @property int|null $category_id
 * @property string|null $feed_type
 * @property string|null $language
 * @property string|null $site_url
 * @property string|null $favicon_url
 * @property string|null $etag
 * @property string|null $last_modified_header
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property string|null $hub_url
 * @property string|null $websub_secret
 * @property \Illuminate\Support\Carbon|null $websub_subscribed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Category|null $category
 * @property-read \Illuminate\Database\Eloquent\Collection<int, News> $news
 */
final class Feed extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'url',
        'description',
        'active',
        'category_id',
        'feed_type',
        'language',
        'site_url',
        'favicon_url',
        'etag',
        'last_modified_header',
        'last_fetched_at',
        'hub_url',
        'websub_secret',
        'websub_subscribed_at',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'last_fetched_at' => 'datetime',
            'websub_subscribed_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function news(): HasMany
    {
        return $this->hasMany(News::class);
    }
}
