<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feeds', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url')->unique();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('category_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feeds');
    }
};
