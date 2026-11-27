<?php

namespace App\Http\Controllers\Api\Gym;

use App\Actions\Gym\GenerateInvoiceCharge;
use App\Actions\Gym\RecordInvoicePayment;
use App\Actions\Gym\RefundPayment;
use App\Http\Controllers\Controller;
use App\Http\Resources\Gym\GymInvoiceResource;
use App\Models\Gym\GymAuditLog;
use App\Models\Gym\GymInvoice;
use App\Models\Gym\GymPayment;
use App\Models\Gym\GymSubscription;
use App\Models\Gym\GymUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var GymUser $user */
        $user = $request->user();
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['pendente', 'vencida', 'paga', 'cancelada'])],
            'student_id' => ['nullable', 'integer'],
        ]);

        $invoices = $this->visibleInvoices($user)
            ->with('student')
            ->when($validated['student_id'] ?? null, fn (Builder $query, int $studentId) => $query->where('gym_student_id', $studentId))
            ->when($validated['status'] ?? null, function (Builder $query, string $status): void {
                match ($status) {
                    'vencida' => $query->where('status', GymInvoice::STATUS_PENDING)->whereDate('due_on', '<', today()),
                    'pendente' => $query->where('status', GymInvoice::STATUS_PENDING)->whereDate('due_on', '>=', today()),
                    default => $query->where('status', $status),
                };
            })
            ->orderByDesc('due_on')
            ->orderByDesc('id')
            ->paginate(50);

        return GymInvoiceResource::collection($invoices);
    }

    public function show(Request $request, int $invoice): GymInvoiceResource
    {
        /** @var GymUser $user */
        $user = $request->user();

        return GymInvoiceResource::make($this->visibleInvoices($user)->with('student', 'payments')->findOrFail($invoice));
    }

    /**
     * Generates the boleto / Pix (copia e cola) / card charge for a pending invoice.
     */
    public function charge(Request $request, int $invoice, GenerateInvoiceCharge $generateInvoiceCharge): GymInvoiceResource
    {
        /** @var GymUser $user */
        $user = $request->user();
        $invoice = $this->visibleInvoices($user)->findOrFail($invoice);
        $validated = $request->validate(['method' => ['required', Rule::in(GymSubscription::PAYMENT_METHODS)]]);

        return GymInvoiceResource::make($generateInvoiceCharge->handle($invoice, $validated['method'])->load('student'));
    }

    /**
     * Front-desk settlement (e.g. Pix confirmed manually, card machine, cash).
     */
    public function pay(Request $request, int $invoice, RecordInvoicePayment $recordInvoicePayment): JsonResponse
    {
        /** @var GymUser $user */
        $user = $request->user();
        $invoice = $this->visibleInvoices($user)->findOrFail($invoice);
        $validated = $request->validate([
            'method' => ['required', Rule::in(GymPayment::METHODS)],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
        ]);

        $recordInvoicePayment->handle($invoice, [
            'method' => $validated['method'],
            'amount' => (string) $validated['amount'],
            'source' => 'manual',
            'paid_at' => now(),
            'recorded_by_user_id' => $user->id,
        ]);

        return GymInvoiceResource::make($invoice->refresh()->load('student', 'payments'))->response()->setStatusCode(201);
    }

    public function cancel(Request $request, int $invoice): GymInvoiceResource
    {
        /** @var GymUser $user */
        $user = $request->user();
        $invoice = $this->visibleInvoices($user)->findOrFail($invoice);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        if ($invoice->status !== GymInvoice::STATUS_PENDING) {
            throw ValidationException::withMessages(['invoice' => 'Somente mensalidades pendentes podem ser canceladas.']);
        }

        $invoice->forceFill([
            'status' => GymInvoice::STATUS_CANCELED,
            'canceled_at' => now(),
            'cancellation_reason' => $validated['reason'],
        ])->save();
        GymAuditLog::record($request, $user, 'financeiro.mensalidade_cancelada', $invoice, ['reason' => $validated['reason']]);

        return GymInvoiceResource::make($invoice->load('student', 'payments'));
    }

    public function refund(Request $request, int $payment, RefundPayment $refundPayment): GymInvoiceResource
    {
        /** @var GymUser $user */
        $user = $request->user();
        $payment = GymPayment::query()->where('gym_id', $user->gym_id)->findOrFail($payment);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $refundPayment->handle($payment, $validated['reason'], $user->id);
        GymAuditLog::record($request, $user, 'financeiro.pagamento_estornado', $payment, ['reason' => $validated['reason']]);

        return GymInvoiceResource::make($payment->invoice()->with('student', 'payments')->firstOrFail());
    }

    /**
     * @return Builder<GymInvoice>
     */
    private function visibleInvoices(GymUser $user): Builder
    {
        return GymInvoice::query()
            ->where('gym_id', $user->gym_id)
            ->when($user->restrictedUnitId() !== null, fn (Builder $query) => $query->whereIn('gym_student_id', $user->visibleStudents()->select('id')));
    }
}
