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
        Schema::create('gym_access_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained();
            $table->foreignId('gym_unit_id')->constrained();
            $table->string('name');
            $table->string('vendor');
            $table->char('token_hash', 64)->unique();
            $table->string('status')->default('ativo');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gym_access_devices');
    }
};
