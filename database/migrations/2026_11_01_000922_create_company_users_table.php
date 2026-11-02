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
        Schema::create('company_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->char('cpf', 11);
            $table->string('name');
            $table->string('username');
            $table->string('password');
            $table->string('role')->default('Funcionario');
            $table->string('status')->default('ativa');
            $table->rememberToken();
            $table->timestamps();

            $table->unique(['company_id', 'cpf']);
            $table->unique(['company_id', 'username']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_users');
    }
};
