<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Widen varchar(255) columns that can receive arbitrarily long values from
     * RSS/Atom feeds (long titles, full-URL guids, long author strings, etc.).
     *
     * Affected columns:
     *   - title         : feed post titles regularly exceed 255 chars
     *   - link          : URLs have no practical length limit
     *   - author        : author names/credentials can be long
     *   - guid          : many feeds use the full permalink as the GUID
     *   - thumbnail_url : scraped og:image URLs can be long
     */
    public function up(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->text('title')->change();
            $table->text('link')->change();
            $table->text('author')->nullable()->change();
            $table->text('guid')->nullable()->change();
            $table->text('thumbnail_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->string('title')->change();
            $table->string('link')->change();
            $table->string('author')->nullable()->change();
            $table->string('guid')->nullable()->change();
            $table->string('thumbnail_url')->nullable()->change();
        });
    }
};
