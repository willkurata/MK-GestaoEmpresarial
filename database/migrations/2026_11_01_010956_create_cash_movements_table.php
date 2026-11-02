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
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('cash_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_user_id')->constrained()->restrictOnDelete();
            $table->string('type');
            $table->string('method');
            $table->decimal('amount', 12, 2);
            $table->string('description');
            $table->dateTime('occurred_at');
            $table->timestamps();
            $table->index(['company_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};
