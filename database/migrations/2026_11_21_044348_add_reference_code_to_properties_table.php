<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('reference_code', 20)->nullable()->unique()->after('slug');
        });

        DB::table('properties')->whereNull('reference_code')->orderBy('id')->each(function (object $property): void {
            DB::table('properties')->where('id', $property->id)->update([
                'reference_code' => 'MK-'.str_pad((string) $property->id, 5, '0', STR_PAD_LEFT),
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropUnique(['reference_code']);
            $table->dropColumn('reference_code');
        });
    }
};
