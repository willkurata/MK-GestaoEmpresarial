<?php

namespace App\Http\Controllers\Api\Gym;

use App\Actions\Gym\IssueInvoice;
use App\Http\Controllers\Controller;
use App\Http\Resources\Gym\GymInvoiceResource;
use App\Http\Resources\Gym\GymSubscriptionResource;
use App\Models\Gym\GymInvoice;
use App\Models\Gym\GymSubscription;
use App\Models\Gym\GymUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SubscriptionController extends Controller
{
    public function index(Request $request, int $student): AnonymousResourceCollection
    {
        /** @var GymUser $user */
        $user = $request->user();
        $student = $user->visibleStudents()->findOrFail($student);

        return GymSubscriptionResource::collection($student->subscriptions()->with('plan')->latest('id')->get());
    }

    /**
     * Enrols the student on a plan and issues the first invoice immediately.
     */
    public function store(Request $request, int $student, IssueInvoice $issueInvoice): JsonResponse
    {
        /** @var GymUser $user */
        $user = $request->user();
        $student = $user->visibleStudents()->whereNull('anonymized_at')->findOrFail($student);
        $validated = $request->validate([
            'plan_id' => ['required', 'integer', Rule::exists('gym_plans', 'id')->where('gym_id', $user->gym_id)->where('status', 'ativo')],
            'payment_method' => ['required', Rule::in(GymSubscription::PAYMENT_METHODS)],
            'starts_on' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        if (! $student->isActive()) {
            throw ValidationException::withMessages(['student' => 'Reative o aluno antes de matriculá-lo.']);
        }

        if ($student->subscriptions()->where('status', 'ativa')->exists()) {
            throw ValidationException::withMessages(['student' => 'O aluno já possui uma matrícula ativa. Cancele-a antes de trocar de plano.']);
        }

        $plan = $user->gym->plans()->findOrFail((int) $validated['plan_id']);
        $startsOn = $validated['starts_on'] ?? today()->toDateString();

        [$subscription, $invoice] = DB::transaction(function () use ($student, $plan, $validated, $startsOn, $issueInvoice): array {
            $subscription = $student->subscriptions()->create([
                'gym_id' => $student->gym_id,
                'gym_plan_id' => $plan->id,
                'price' => $plan->price,
                'billing_interval_months' => $plan->billing_interval_months,
                'payment_method' => $validated['payment_method'],
                'status' => 'ativa',
                'starts_on' => $startsOn,
                'next_billing_on' => $startsOn,
            ]);

            return [$subscription, $issueInvoice->handle($subscription)];
        });

        return response()->json([
            'data' => GymSubscriptionResource::make($subscription->refresh()->load('plan')),
            'first_invoice' => GymInvoiceResource::make($invoice->refresh()),
        ], 201);
    }

    /**
     * Cancels the contract and any invoice not yet due; past-due invoices remain as debt.
     */
    public function cancel(Request $request, int $subscription): GymSubscriptionResource
    {
        /** @var GymUser $user */
        $user = $request->user();
        $subscription = GymSubscription::query()
            ->where('gym_id', $user->gym_id)
            ->whereIn('gym_student_id', $user->visibleStudents()->select('id'))
            ->findOrFail($subscription);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        if (! $subscription->isActive()) {
            throw ValidationException::withMessages(['subscription' => 'Esta matrícula já foi cancelada.']);
        }

        DB::transaction(function () use ($subscription, $validated): void {
            $subscription->forceFill([
                'status' => 'cancelada',
                'canceled_at' => now(),
                'cancellation_reason' => $validated['reason'],
            ])->save();

            $subscription->invoices()
                ->where('status', GymInvoice::STATUS_PENDING)
                ->whereDate('due_on', '>=', today())
                ->update(['status' => GymInvoice::STATUS_CANCELED, 'canceled_at' => now(), 'cancellation_reason' => 'Matrícula cancelada']);
        });

        return GymSubscriptionResource::make($subscription->load('plan'));
    }
}
