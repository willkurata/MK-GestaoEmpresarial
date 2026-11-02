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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('sale_id')->unique()->constrained()->restrictOnDelete();
            $table->string('method');
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('confirmado');
            $table->dateTime('paid_at');
            $table->decimal('amount_received', 12, 2)->nullable();
            $table->decimal('change_amount', 12, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
