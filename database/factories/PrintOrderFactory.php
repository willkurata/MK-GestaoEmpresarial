<?php

namespace Database\Factories;

use App\Models\PrintOrder;
use App\Models\Totem;
use App\PrintOrderStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;

/**
 * @extends Factory<PrintOrder>
 */
class PrintOrderFactory extends Factory
{
    /** Plain customer token used by every factory order. */
    public const string ORDER_TOKEN = 'factory-order-token';

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'totem_id' => Totem::factory(),
            'order_token_hash' => Totem::hashSecret(self::ORDER_TOKEN),
            'pickup_code' => Totem::randomFromAlphabet(3),
            'status' => PrintOrderStatus::Draft,
            'expires_at' => now()->addMinutes(30),
        ];
    }

    /**
     * A 2-page black-and-white PDF already analyzed and priced (2 × R$ 0,50).
     */
    public function priced(): static
    {
        return $this->state(fn (array $attributes) => [
            'file_name' => 'documento.pdf',
            'file_size' => 1024,
            'file_mime' => 'application/pdf',
            'file_path' => 'totem/test/'.fake()->uuid().'.pdf',
            'file_sha256' => hash('sha256', 'pdf'),
            'page_count' => 2,
            'file_analyzed_at' => now(),
            'file_purge_at' => now()->addDay(),
            'quote_pages' => 2,
            'quote_sides' => 2,
            'quote_sheets' => 2,
            'quote_per_side' => '0.50',
            'quote_total' => '1.00',
        ])->afterCreating(function (PrintOrder $order): void {
            Storage::disk(config('totem.disk'))->put($order->file_path, '%PDF-1.4 fake');
        });
    }

    /**
     * Paid and waiting in the totem queue.
     */
    public function queued(): static
    {
        return $this->priced()
            ->state(fn (array $attributes) => ['status' => PrintOrderStatus::Queued, 'expires_at' => null])
            ->afterCreating(function (PrintOrder $order): void {
                $order->payment()->create([
                    'provider' => 'sandbox',
                    'external_id' => 'sbx_'.fake()->uuid(),
                    'amount' => $order->quote_total,
                    'status' => 'paid',
                    'copy_paste' => 'SANDBOX-PIX',
                    'expires_at' => now()->addMinutes(15),
                    'paid_at' => now(),
                ]);
            });
    }
}
