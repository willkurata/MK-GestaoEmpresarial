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
        Schema::create('totems', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('name');
            $table->string('location')->nullable();
            $table->string('help_contact')->nullable();
            $table->string('status')->default('active');
            $table->char('api_token_hash', 64)->nullable()->unique();
            $table->char('pairing_code_hash', 64)->nullable();
            $table->dateTime('pairing_code_expires_at')->nullable();
            $table->dateTime('paired_at')->nullable();
            $table->dateTime('last_seen_at')->nullable();
            $table->string('agent_version', 32)->nullable();
            $table->string('printer_state', 16)->default('offline');
            $table->json('printer_reasons')->nullable();
            $table->unsignedTinyInteger('paper_level')->nullable();
            $table->json('printer_markers')->nullable();
            $table->boolean('supports_color')->default(false);
            $table->boolean('supports_duplex')->default(false);
            $table->decimal('price_bw_per_side', 8, 2);
            $table->decimal('price_color_per_side', 8, 2)->nullable();
            $table->unsignedSmallInteger('max_copies')->default(20);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('totems');
    }
};
