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
        Schema::create('gym_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained();
            $table->foreignId('gym_invoice_id')->constrained();
            $table->string('method');
            $table->decimal('amount', 10, 2);
            $table->string('status')->default('confirmado');
            $table->string('source');
            $table->string('gateway_payment_id')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('gym_users');
            $table->timestamp('paid_at');
            $table->timestamp('refunded_at')->nullable();
            $table->string('refund_reason')->nullable();
            $table->foreignId('refunded_by_user_id')->nullable()->constrained('gym_users');
            $table->timestamps();

            $table->unique(['gym_id', 'gateway_payment_id']);
            $table->index(['gym_id', 'paid_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gym_payments');
    }
};
