<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('feeds', function (Blueprint $table): void {
            $table->boolean('disable_full_article_scraping')->default(false)->after('hub_url');
            $table->boolean('hide_image_in_detail')->default(false)->after('disable_full_article_scraping');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feeds', function (Blueprint $table): void {
            $table->dropColumn(['disable_full_article_scraping', 'hide_image_in_detail']);
        });
    }
};
