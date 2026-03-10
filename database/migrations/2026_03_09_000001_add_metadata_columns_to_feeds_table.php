<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feeds', function (Blueprint $table) {
            $table->string('feed_type')->nullable()->after('active');
            $table->string('language', 20)->nullable()->after('feed_type');
            $table->string('site_url')->nullable()->after('language');
            $table->string('favicon_url')->nullable()->after('site_url');
            $table->string('etag')->nullable()->after('favicon_url');
            $table->string('last_modified_header')->nullable()->after('etag');
            $table->timestamp('last_fetched_at')->nullable()->after('last_modified_header');
        });
    }

    public function down(): void
    {
        Schema::table('feeds', function (Blueprint $table) {
            $table->dropColumn([
                'feed_type',
                'language',
                'site_url',
                'favicon_url',
                'etag',
                'last_modified_header',
                'last_fetched_at',
            ]);
        });
    }
};
