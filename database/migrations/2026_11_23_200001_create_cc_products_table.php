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
        Schema::create('cc_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('cc_stores');
            $table->string('name', 120);
            $table->string('category', 20)->default('produto');
            $table->decimal('price', 12, 2);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['store_id', 'name']);
            $table->index(['store_id', 'category', 'active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cc_products');
    }
};
