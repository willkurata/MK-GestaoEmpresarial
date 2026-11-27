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
        Schema::create('gym_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained();
            $table->foreignId('gym_unit_id')->nullable()->constrained();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->string('role');
            $table->string('status')->default('ativo');
            $table->timestamps();

            $table->unique(['gym_id', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gym_users');
    }
};
