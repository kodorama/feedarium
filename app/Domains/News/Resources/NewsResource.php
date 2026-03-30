<?php

namespace App\Domains\News\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\News
 */
final class NewsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'link' => $this->link,
            'description' => $this->description,
            'thumbnail_url' => $this->thumbnail_url,
            'full_body' => $this->full_body,
            'published_at' => $this->published_at?->toIso8601String(),
            'author' => $this->author,
            'is_read' => (bool) $this->is_read,
            'feed' => [
                'id' => $this->feed->id,
                'name' => $this->feed->name,
                'favicon_url' => $this->feed->favicon_url,
            ],
        ];
    }
}
