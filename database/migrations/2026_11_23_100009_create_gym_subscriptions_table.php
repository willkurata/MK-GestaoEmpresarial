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
        Schema::create('gym_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained();
            $table->foreignId('gym_student_id')->constrained();
            $table->foreignId('gym_plan_id')->constrained();
            $table->decimal('price', 10, 2);
            $table->unsignedTinyInteger('billing_interval_months');
            $table->string('payment_method');
            $table->string('status')->default('ativa');
            $table->date('starts_on');
            $table->date('next_billing_on');
            $table->timestamp('canceled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['gym_id', 'status', 'next_billing_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gym_subscriptions');
    }
};
