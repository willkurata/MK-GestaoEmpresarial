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
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('cash_register_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_user_id')->constrained()->restrictOnDelete();
            $table->dateTime('opened_at');
            $table->decimal('opening_amount', 12, 2);
            $table->string('status')->default('aberto');
            $table->dateTime('closed_at')->nullable();
            $table->decimal('expected_amount', 12, 2)->nullable();
            $table->decimal('counted_amount', 12, 2)->nullable();
            $table->decimal('difference_amount', 12, 2)->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status']);
            $table->index(['cash_register_id', 'opened_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_sessions');
    }
};
