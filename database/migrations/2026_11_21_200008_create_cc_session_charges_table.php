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
        Schema::create('cc_session_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('cc_stores');
            $table->foreignId('station_session_id')->constrained('cc_station_sessions')->restrictOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('cc_plans')->nullOnDelete();
            $table->string('description');
            $table->unsignedInteger('minutes');
            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cc_session_charges');
    }
};
