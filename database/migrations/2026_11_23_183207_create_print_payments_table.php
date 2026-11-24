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
        Schema::create('print_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('print_order_id')->unique()->constrained()->restrictOnDelete();
            $table->string('provider', 32);
            $table->string('external_id')->unique();
            $table->decimal('amount', 10, 2);
            $table->string('status', 16)->default('pending');
            $table->text('copy_paste');
            $table->dateTime('expires_at');
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('refunded_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('print_payments');
    }
};
