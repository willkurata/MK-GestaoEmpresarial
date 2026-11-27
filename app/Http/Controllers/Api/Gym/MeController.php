<?php

namespace App\Http\Controllers\Api\Gym;

use App\Actions\Gym\GenerateInvoiceCharge;
use App\Http\Controllers\Controller;
use App\Http\Resources\Gym\GymAccessEventResource;
use App\Http\Resources\Gym\GymInvoiceResource;
use App\Http\Resources\Gym\GymStudentResource;
use App\Http\Resources\Gym\GymSubscriptionResource;
use App\Models\Gym\GymAuditLog;
use App\Models\Gym\GymStudent;
use App\Models\Gym\GymSubscription;
use App\Models\Gym\GymUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Student area of the app: only the logged-in student's own data.
 */
class MeController extends Controller
{
    public function profile(Request $request): JsonResponse
    {
        $student = $this->student($request);

        return response()->json([
            'data' => GymStudentResource::make($student->load('activeBiometricConsent')),
            'subscription' => $student->subscriptions()->where('status', 'ativa')->with('plan')->first()
                ?->toResource(GymSubscriptionResource::class),
        ]);
    }

    public function invoices(Request $request): AnonymousResourceCollection
    {
        return GymInvoiceResource::collection($this->student($request)->invoices()->orderByDesc('due_on')->paginate(24));
    }

    /**
     * The student generates the boleto or Pix of one of their own pending invoices.
     */
    public function chargeInvoice(Request $request, int $invoice, GenerateInvoiceCharge $generateInvoiceCharge): GymInvoiceResource
    {
        $invoice = $this->student($request)->invoices()->findOrFail($invoice);
        $validated = $request->validate(['method' => ['required', Rule::in(GymSubscription::PAYMENT_METHODS)]]);

        return GymInvoiceResource::make($generateInvoiceCharge->handle($invoice, $validated['method']));
    }

    public function accessEvents(Request $request): AnonymousResourceCollection
    {
        return GymAccessEventResource::collection($this->student($request)->accessEvents()->orderByDesc('occurred_at')->cursorPaginate(50));
    }

    /**
     * The student may withdraw biometric consent at any time (LGPD art. 8, §5).
     */
    public function revokeBiometricConsent(Request $request): GymStudentResource
    {
        /** @var GymUser $user */
        $user = $request->user();
        $student = $this->student($request);
        BiometricController::revokeFor($student);
        GymAuditLog::record($request, $user, 'biometria.consentimento_revogado_pelo_titular', $student);

        return GymStudentResource::make($student->refresh()->load('activeBiometricConsent'));
    }

    private function student(Request $request): GymStudent
    {
        /** @var GymUser $user */
        $user = $request->user();

        return $user->student()->whereNull('anonymized_at')->firstOrFail();
    }
}
