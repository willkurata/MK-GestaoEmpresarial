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
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->string('slug', 200)->unique();
            $table->string('purpose', 20)->nullable();
            $table->string('type', 30)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('neighborhood', 120)->nullable();
            $table->decimal('price', 14, 2)->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('bedrooms')->nullable();
            $table->unsignedSmallInteger('bathrooms')->nullable();
            $table->unsignedSmallInteger('parking_spaces')->nullable();
            $table->decimal('area', 10, 2)->nullable();
            $table->json('features')->nullable();
            $table->string('status', 20)->default('rascunho');
            $table->unsignedBigInteger('cover_photo_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index(['status', 'purpose', 'city']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
