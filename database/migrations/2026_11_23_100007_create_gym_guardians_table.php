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
        Schema::create('gym_guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained();
            $table->foreignId('gym_student_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('cpf');
            $table->string('relationship');
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gym_guardians');
    }
};
