<?php

namespace App\Http\Controllers\Totem;

use App\Actions\Totem\PrintOrderPayments;
use App\Actions\Totem\PrintQuote;
use App\Actions\Totem\QrSessionToken;
use App\Exceptions\InvalidPrintOrderTransition;
use App\Http\Controllers\Controller;
use App\Http\Resources\Totem\PrintOrderResource;
use App\Jobs\AnalyzePrintOrderFile;
use App\Models\PrintOrder;
use App\Models\Totem;
use App\PrintOrderStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PrintOrderController extends Controller
{
    /**
     * Customer scanned the totem QR code: open a draft order and hand out its private token.
     */
    public function store(Request $request, Totem $totem, QrSessionToken $qrSessionToken): JsonResponse
    {
        abort_unless($totem->isActive(), 404);

        $validated = $request->validate(['token' => ['required', 'string', 'max:64']]);

        if (! $qrSessionToken->isValid($totem, $validated['token'])) {
            return response()->json(['message' => 'O QR code expirou. Leia o código atual na tela do totem.'], 410);
        }

        [$order, $orderToken] = PrintOrder::openDraft($totem);

        return response()->json(['data' => [
            'order_id' => $order->public_id,
            'order_token' => $orderToken,
        ]], 201);
    }

    public function show(Request $request, PrintOrder $order): PrintOrderResource
    {
        return new PrintOrderResource($this->authorizedOrder($request, $order));
    }

    /**
     * Receive (or replace) the file to print. The real MIME type is checked from the file contents.
     */
    public function uploadFile(Request $request, PrintOrder $order): PrintOrderResource
    {
        $order = $this->authorizedOrder($request, $order);
        $this->ensureDraft($order);

        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'max:'.((int) config('totem.max_file_mb') * 1024),
                'extensions:'.implode(',', config('totem.accepted_extensions')),
                'mimetypes:'.implode(',', config('totem.accepted_mimetypes')),
            ],
        ]);

        /** @var UploadedFile $file */
        $file = $validated['file'];
        $disk = (string) config('totem.disk');
        $previousPath = $order->file_path;
        $path = $file->storeAs("totem/{$order->totem_id}", $order->public_id.'-'.Str::lower(Str::random(8)).'.'.$file->extension(), $disk);

        abort_if($path === false, 503, 'Não foi possível guardar o arquivo. Tente novamente.');

        $order->forceFill([
            'file_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'file_size' => $file->getSize(),
            'file_mime' => $file->getMimeType(),
            'file_path' => $path,
            'file_sha256' => hash_file('sha256', $file->getRealPath()),
            'page_count' => null,
            'file_analyzed_at' => null,
            'file_purge_at' => now()->addHours((int) config('totem.file_retention_hours')),
            'file_purged_at' => null,
            ...PrintQuote::empty(),
        ])->save();

        if ($previousPath !== null) {
            Storage::disk($disk)->delete($previousPath);
        }

        AnalyzePrintOrderFile::dispatch($order, $path);

        return new PrintOrderResource($order->refresh());
    }

    /**
     * Change print options and recalculate the quote. Only drafts can change.
     */
    public function update(Request $request, PrintOrder $order): PrintOrderResource
    {
        $order = $this->authorizedOrder($request, $order);
        $this->ensureDraft($order);
        $totem = $order->totem;

        $validated = $request->validate([
            'options' => ['required', 'array'],
            'options.color' => ['sometimes', Rule::in($totem->supports_color ? ['bw', 'color'] : ['bw'])],
            'options.duplex' => ['sometimes', 'boolean', $totem->supports_duplex ? 'nullable' : 'declined'],
            'options.pages_per_sheet' => ['sometimes', 'integer', Rule::in([1, 2, 4])],
            'options.page_range' => ['sometimes', 'nullable', 'string', 'max:100'],
            'options.copies' => ['sometimes', 'integer', 'min:1', 'max:'.$totem->max_copies],
        ]);

        $options = $validated['options'];

        $order->fill(array_filter([
            'color_mode' => $options['color'] ?? null,
            'duplex' => $options['duplex'] ?? null,
            'pages_per_sheet' => $options['pages_per_sheet'] ?? null,
            'copies' => $options['copies'] ?? null,
        ], fn (mixed $value): bool => $value !== null));

        if (array_key_exists('page_range', $options)) {
            $order->page_range = $options['page_range'] === null || trim($options['page_range']) === '' ? null : trim($options['page_range']);
        }

        try {
            $order->fill(PrintQuote::for($order));
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['options.page_range' => $exception->getMessage()]);
        }

        $order->save();

        return new PrintOrderResource($order);
    }

    /**
     * Create the Pix charge. The totem must be online so nobody pays for a print that cannot happen.
     */
    public function checkout(Request $request, PrintOrder $order, PrintOrderPayments $payments): PrintOrderResource
    {
        $order = $this->authorizedOrder($request, $order);

        if ($order->status === PrintOrderStatus::Draft && (! $order->totem->isActive() || ! $order->totem->isOnline())) {
            abort(409, 'O totem está offline no momento. Tente novamente em instantes.');
        }

        return new PrintOrderResource($payments->checkout($order)->refresh());
    }

    /**
     * Customer gives up before paying. Paid orders are not canceled here; support refunds them.
     */
    public function cancel(Request $request, PrintOrder $order): PrintOrderResource
    {
        $order = $this->authorizedOrder($request, $order);

        DB::transaction(function () use ($order): void {
            $order = PrintOrder::query()->lockForUpdate()->findOrFail($order->id);
            $order->transitionTo(PrintOrderStatus::Canceled, 'customer', ['expires_at' => null]);
            $order->payment()->where('status', 'pending')->update(['status' => 'canceled']);
        });

        $order->refresh()->purgeFile();

        return new PrintOrderResource($order->refresh());
    }

    /**
     * Wrong token answers 404 so order ids cannot be probed.
     */
    private function authorizedOrder(Request $request, PrintOrder $order): PrintOrder
    {
        abort_unless($order->tokenMatches($request->header('X-Order-Token')), 404);

        return $order->load(['totem', 'payment']);
    }

    private function ensureDraft(PrintOrder $order): void
    {
        if ($order->status !== PrintOrderStatus::Draft) {
            throw new InvalidPrintOrderTransition($order->status, PrintOrderStatus::Draft);
        }

        if ($order->expires_at !== null && $order->expires_at->isPast()) {
            abort(409, 'Este pedido expirou. Leia o QR code novamente.');
        }
    }
}
