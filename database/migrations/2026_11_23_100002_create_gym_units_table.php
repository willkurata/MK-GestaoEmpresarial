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
        Schema::create('gym_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained();
            $table->string('name');
            $table->string('address')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->string('status')->default('ativa');
            $table->timestamps();

            $table->unique(['gym_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gym_units');
    }
};
