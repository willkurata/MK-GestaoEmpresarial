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
        Schema::create('cc_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('cc_stores');
            $table->string('name');
            $table->string('billing');
            $table->decimal('price', 12, 2)->nullable();
            $table->unsignedInteger('minutes')->nullable();
            $table->decimal('hourly_rate', 12, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cc_plans');
    }
};
