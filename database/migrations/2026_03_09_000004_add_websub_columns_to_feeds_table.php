<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feeds', function (Blueprint $table) {
            $table->string('hub_url')->nullable()->after('last_fetched_at');
            $table->string('websub_secret')->nullable()->after('hub_url');
            $table->timestamp('websub_subscribed_at')->nullable()->after('websub_secret');
        });
    }

    public function down(): void
    {
        Schema::table('feeds', function (Blueprint $table) {
            $table->dropColumn(['hub_url', 'websub_secret', 'websub_subscribed_at']);
        });
    }
};
