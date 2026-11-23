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
        Schema::create('cc_print_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('cc_stores');
            $table->foreignId('requested_by')->constrained('cc_attendants');
            $table->foreignId('station_session_id')->nullable()->constrained('cc_station_sessions')->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('cc_payments')->restrictOnDelete();
            $table->string('kind');
            $table->unsignedTinyInteger('width');
            $table->text('content');
            $table->text('payload');
            $table->string('status')->default('pendente');
            $table->dateTime('printed_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cc_print_jobs');
    }
};
