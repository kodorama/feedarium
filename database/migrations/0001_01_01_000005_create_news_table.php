<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feed_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('link');
            $table->text('description')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('author')->nullable();
            $table->string('guid')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news');
    }
};
