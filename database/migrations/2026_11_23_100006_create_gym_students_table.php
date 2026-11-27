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
        Schema::create('gym_students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained();
            $table->foreignId('gym_unit_id')->constrained();
            $table->foreignId('gym_user_id')->nullable()->unique()->constrained();
            $table->string('name');
            $table->text('cpf')->nullable();
            $table->char('cpf_hash', 64)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('status')->default('ativo');
            $table->uuid('access_code')->unique();
            $table->string('face_status')->default('nao_cadastrada');
            $table->timestamp('face_enrolled_at')->nullable();
            $table->timestamp('anonymized_at')->nullable();
            $table->timestamps();

            $table->unique(['gym_id', 'cpf_hash']);
            $table->index(['gym_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gym_students');
    }
};
