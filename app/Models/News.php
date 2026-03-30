<?php

namespace App\Models;

use Laravel\Scout\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $feed_id
 * @property string $title
 * @property string $link
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property string|null $author
 * @property string|null $guid
 * @property bool $is_read
 * @property string|null $thumbnail_url
 * @property string|null $full_body
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Feed $feed
 * @property-read Collection<int, User> $savedByUsers
 */
final class News extends Model
{
    use HasFactory;
    use Searchable;

    protected $fillable = [
        'feed_id',
        'title',
        'link',
        'description',
        'published_at',
        'author',
        'guid',
        'is_read',
        'thumbnail_url',
        'full_body',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_read' => 'boolean',
        ];
    }

    public function searchableAs(): string
    {
        return 'news';
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'feed_id' => (int) $this->feed_id,
            'title' => $this->title,
            'description' => $this->description ? strip_tags($this->description) : null,
            'author' => $this->author,
            'published_at' => $this->published_at?->timestamp,
        ];
    }

    public function feed(): BelongsTo
    {
        return $this->belongsTo(Feed::class);
    }

    public function savedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'saved_articles')
            ->withPivot('created_at');
    }
}
