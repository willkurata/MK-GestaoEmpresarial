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
        Schema::create('gym_access_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained();
            $table->foreignId('gym_unit_id')->constrained();
            $table->foreignId('gym_access_device_id')->nullable()->constrained();
            $table->foreignId('gym_student_id')->nullable()->constrained();
            $table->string('direction');
            $table->string('method');
            $table->string('decision');
            $table->string('reason')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('gym_users');
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['gym_id', 'gym_unit_id', 'occurred_at']);
            $table->index(['gym_student_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gym_access_events');
    }
};
