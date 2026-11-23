<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Charges become the session's itemized bill: time ("tempo"), sold
     * products ("produto") and printed pages ("impressao").
     */
    public function up(): void
    {
        Schema::table('cc_session_charges', function (Blueprint $table) {
            $table->string('kind', 20)->default('tempo')->after('plan_id');
            $table->foreignId('product_id')->nullable()->after('kind')->constrained('cc_products')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->after('product_id')->constrained('cc_attendants');
            $table->unsignedInteger('quantity')->default(1)->after('minutes');
            $table->decimal('unit_price', 12, 2)->nullable()->after('quantity');
            $table->boolean('manual_price')->default(false)->after('unit_price');
            $table->string('external_reference', 100)->nullable()->after('manual_price');

            $table->index(['store_id', 'kind', 'created_at']);
            $table->unique(['station_session_id', 'external_reference']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cc_session_charges', function (Blueprint $table) {
            $table->dropUnique(['station_session_id', 'external_reference']);
            $table->dropIndex(['store_id', 'kind', 'created_at']);
            $table->dropConstrainedForeignId('product_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['kind', 'quantity', 'unit_price', 'manual_price', 'external_reference']);
        });
    }
};
