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
        Schema::create('print_orders', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('totem_id')->constrained()->restrictOnDelete();
            $table->char('order_token_hash', 64);
            $table->char('pickup_code', 3);
            $table->string('status', 24)->default('draft');

            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('file_mime', 64)->nullable();
            $table->string('file_path')->nullable();
            $table->char('file_sha256', 64)->nullable();
            $table->unsignedInteger('page_count')->nullable();
            $table->dateTime('file_analyzed_at')->nullable();
            $table->dateTime('file_purge_at')->nullable();
            $table->dateTime('file_purged_at')->nullable();

            $table->string('color_mode', 8)->default('bw');
            $table->boolean('duplex')->default(false);
            $table->unsignedTinyInteger('pages_per_sheet')->default(1);
            $table->string('page_range')->nullable();
            $table->unsignedSmallInteger('copies')->default(1);

            $table->unsignedInteger('quote_pages')->nullable();
            $table->unsignedInteger('quote_sides')->nullable();
            $table->unsignedInteger('quote_sheets')->nullable();
            $table->decimal('quote_per_side', 8, 2)->nullable();
            $table->decimal('quote_total', 10, 2)->nullable();

            $table->unsignedInteger('sheets_done')->default(0);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('claimed_at')->nullable();
            $table->dateTime('lease_expires_at')->nullable();
            $table->dateTime('ready_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['totem_id', 'status']);
            $table->index(['totem_id', 'pickup_code']);
            $table->index(['status', 'expires_at']);
            $table->index(['status', 'lease_expires_at']);
            $table->index('file_purge_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('print_orders');
    }
};
