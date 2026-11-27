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
        Schema::create('gym_class_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained();
            $table->foreignId('gym_class_id')->constrained();
            $table->foreignId('gym_student_id')->constrained();
            $table->date('attended_on');
            $table->boolean('present');
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('gym_users');
            $table->timestamps();

            $table->unique(['gym_class_id', 'gym_student_id', 'attended_on']);
            $table->index(['gym_id', 'attended_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gym_class_attendances');
    }
};
