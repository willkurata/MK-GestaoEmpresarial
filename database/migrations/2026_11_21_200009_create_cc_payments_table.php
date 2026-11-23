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
        Schema::create('cc_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('cc_stores');
            $table->foreignId('customer_id')->nullable()->constrained('cc_customers')->restrictOnDelete();
            $table->foreignId('station_session_id')->nullable()->constrained('cc_station_sessions')->restrictOnDelete();
            $table->foreignId('received_by')->constrained('cc_attendants');
            $table->string('purpose');
            $table->string('method');
            $table->decimal('amount', 12, 2);
            $table->decimal('amount_received', 12, 2)->nullable();
            $table->decimal('change_amount', 12, 2)->nullable();
            $table->string('reference', 100)->nullable();
            $table->dateTime('paid_at');
            $table->timestamps();

            $table->index(['store_id', 'paid_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cc_payments');
    }
};
