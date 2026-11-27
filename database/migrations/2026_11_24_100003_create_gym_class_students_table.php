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
        Schema::create('gym_class_students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_class_id')->constrained();
            $table->foreignId('gym_student_id')->constrained();
            $table->date('joined_on');
            $table->date('left_on')->nullable();
            $table->timestamps();

            $table->index(['gym_class_id', 'left_on']);
            $table->index(['gym_student_id', 'left_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gym_class_students');
    }
};
