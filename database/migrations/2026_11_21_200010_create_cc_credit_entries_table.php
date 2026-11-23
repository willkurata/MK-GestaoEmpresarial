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
        Schema::create('cc_credit_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('cc_stores');
            $table->foreignId('customer_id')->constrained('cc_customers')->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('cc_payments')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('cc_attendants');
            $table->string('type');
            $table->decimal('amount', 12, 2);
            $table->string('description');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cc_credit_entries');
    }
};
