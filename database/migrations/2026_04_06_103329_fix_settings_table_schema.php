<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Use raw SQL to avoid any Laravel schema-builder column cache.
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $hasColumn = collect(DB::select('PRAGMA table_info(settings)'))
                ->pluck('name')
                ->contains('setting_key');
        } else {
            $hasColumn = (bool) DB::selectOne(
                "SELECT column_name FROM information_schema.columns WHERE table_name = 'settings' AND column_name = 'setting_key'"
            );
        }

        if ($hasColumn) {
            return;
        }

        // Table exists with wrong schema — drop via raw statement and rebuild.
        DB::statement('DROP TABLE IF EXISTS settings');

        Schema::create('settings', function (Blueprint $table) {
            $table->string('setting_key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        DB::table('settings')->insert([
            ['setting_key' => 'news_retention_enabled', 'value' => 'true', 'created_at' => now(), 'updated_at' => now()],
            ['setting_key' => 'news_retention_days', 'value' => '90', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
