<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Columns for shifts left open from a previous day (WIL-42): how the shift was closed,
     * who closed it, when the operator chose to keep it, and the cash carried to the next shift.
     */
    public function up(): void
    {
        Schema::table('cash_sessions', function (Blueprint $table) {
            $table->string('closing_mode')->nullable()->after('status');
            $table->foreignId('closed_by_company_user_id')->nullable()->after('closed_at')
                ->constrained('company_users')->restrictOnDelete();
            $table->dateTime('continued_at')->nullable()->after('closed_by_company_user_id');
            $table->foreignId('previous_cash_session_id')->nullable()->after('continued_at')
                ->constrained('cash_sessions')->restrictOnDelete();
            $table->decimal('transferred_amount', 12, 2)->nullable()->after('previous_cash_session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('previous_cash_session_id');
            $table->dropConstrainedForeignId('closed_by_company_user_id');
            $table->dropColumn(['closing_mode', 'continued_at', 'transferred_amount']);
        });
    }
};
