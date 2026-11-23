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
        Schema::create('cc_stations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('cc_stores');
            $table->string('name', 60);
            $table->string('type', 30)->default('pc');
            $table->string('status')->default('livre');
            $table->dateTime('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cc_stations');
    }
};
