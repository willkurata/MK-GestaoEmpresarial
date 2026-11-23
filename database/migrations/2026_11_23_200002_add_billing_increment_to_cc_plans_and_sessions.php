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
        Schema::table('cc_plans', function (Blueprint $table) {
            $table->unsignedSmallInteger('billing_increment_minutes')->nullable()->after('hourly_rate');
        });

        Schema::table('cc_station_sessions', function (Blueprint $table) {
            $table->unsignedSmallInteger('billing_increment_minutes')->default(1)->after('hourly_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cc_station_sessions', function (Blueprint $table) {
            $table->dropColumn('billing_increment_minutes');
        });

        Schema::table('cc_plans', function (Blueprint $table) {
            $table->dropColumn('billing_increment_minutes');
        });
    }
};
