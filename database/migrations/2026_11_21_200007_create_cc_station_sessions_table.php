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
        Schema::create('cc_station_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('cc_stores');
            $table->foreignId('station_id')->constrained('cc_stations')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('cc_customers')->restrictOnDelete();
            $table->foreignId('opened_by')->constrained('cc_attendants');
            $table->foreignId('closed_by')->nullable()->constrained('cc_attendants');
            $table->string('billing');
            $table->decimal('hourly_rate', 12, 2)->nullable();
            $table->dateTime('started_at');
            $table->dateTime('ends_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->decimal('amount_due', 12, 2)->default(0);
            $table->string('status')->default('ativa');
            $table->timestamps();

            $table->index(['store_id', 'status']);
            $table->index(['station_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cc_station_sessions');
    }
};
