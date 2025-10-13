<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class News extends Model
{
    use HasFactory;

    protected $fillable = [
        'feed_id',
        'title',
        'link',
        'description',
        'published_at',
        'author',
        'guid',
    ];

    public function feed()
    {
        return $this->belongsTo(Feed::class);
    }
}
