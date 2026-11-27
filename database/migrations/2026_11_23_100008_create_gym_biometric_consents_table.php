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
        Schema::create('gym_biometric_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained();
            $table->foreignId('gym_student_id')->constrained();
            $table->foreignId('gym_guardian_id')->nullable()->constrained()->nullOnDelete();
            $table->string('granted_by');
            $table->string('terms_version');
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('gym_users');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('granted_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['gym_student_id', 'revoked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gym_biometric_consents');
    }
};
