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
        Schema::table('gym_students', function (Blueprint $table) {
            $table->string('registration_number', 12)->nullable()->after('access_code');
            $table->string('access_pin')->nullable()->after('registration_number');
            $table->unsignedTinyInteger('access_pin_failed_attempts')->default(0)->after('access_pin');
            $table->timestamp('access_pin_locked_at')->nullable()->after('access_pin_failed_attempts');

            $table->unique(['gym_id', 'registration_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gym_students', function (Blueprint $table) {
            $table->dropUnique(['gym_id', 'registration_number']);
            $table->dropColumn(['registration_number', 'access_pin', 'access_pin_failed_attempts', 'access_pin_locked_at']);
        });
    }
};
