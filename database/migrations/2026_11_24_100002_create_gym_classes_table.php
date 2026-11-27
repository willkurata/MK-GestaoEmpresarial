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
        Schema::create('gym_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained();
            $table->foreignId('gym_unit_id')->constrained();
            $table->string('name');
            $table->string('modality')->nullable();
            $table->string('instructor_name')->nullable();
            $table->json('schedule');
            $table->unsignedInteger('capacity')->nullable();
            $table->string('status')->default('ativa');
            $table->timestamps();

            $table->index(['gym_id', 'gym_unit_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gym_classes');
    }
};
