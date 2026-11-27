<?php

namespace App\Http\Controllers\Api\Gym;

use App\Http\Controllers\Controller;
use App\Models\Gym\GymAccessEvent;
use App\Models\Gym\GymAuditLog;
use App\Models\Gym\GymBiometricConsent;
use App\Models\Gym\GymGuardian;
use App\Models\Gym\GymInvoice;
use App\Models\Gym\GymStudent;
use App\Models\Gym\GymUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * LGPD data-subject rights: access/portability (export) and elimination (anonymisation).
 * Financial records are kept (legal obligation) but detached from personal identifiers.
 */
class PrivacyController extends Controller
{
    public function export(Request $request, int $student): JsonResponse
    {
        /** @var GymUser $user */
        $user = $request->user();
        $student = $user->visibleStudents()->whereNull('anonymized_at')->findOrFail($student);
        GymAuditLog::record($request, $user, 'lgpd.dados_exportados', $student);

        return response()->json(['data' => self::personalData($student)]);
    }

    /**
     * Same export for the logged-in student (art. 18, II and V).
     */
    public function exportOwn(Request $request): JsonResponse
    {
        /** @var GymUser $user */
        $user = $request->user();
        $student = $user->student()->firstOrFail();
        GymAuditLog::record($request, $user, 'lgpd.dados_exportados_pelo_titular', $student);

        return response()->json(['data' => self::personalData($student)]);
    }

    public function anonymize(Request $request, int $student): JsonResponse
    {
        /** @var GymUser $user */
        $user = $request->user();
        $student = $user->visibleStudents()->whereNull('anonymized_at')->findOrFail($student);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        DB::transaction(function () use ($student): void {
            BiometricController::revokeFor($student);
            $student->subscriptions()->where('status', 'ativa')->update([
                'status' => 'cancelada',
                'canceled_at' => now(),
                'cancellation_reason' => 'Anonimização LGPD',
            ]);
            $student->invoices()->where('status', GymInvoice::STATUS_PENDING)->update([
                'status' => GymInvoice::STATUS_CANCELED,
                'canceled_at' => now(),
                'cancellation_reason' => 'Anonimização LGPD',
            ]);
            $student->guardians()->delete();

            if ($student->user !== null) {
                $student->user->apiTokens()->delete();
                $student->user->forceFill([
                    'status' => 'inativo',
                    'name' => 'Aluno anonimizado',
                    'email' => 'anonimizado+'.$student->user->id.'@invalid.local',
                    'password' => Str::random(40),
                ])->save();
            }

            $student->forceFill([
                'name' => 'Aluno anonimizado #'.$student->id,
                'cpf' => null,
                'email' => null,
                'phone' => null,
                'birth_date' => null,
                'status' => 'inativo',
                'access_code' => (string) Str::uuid(),
                'access_pin' => null,
                'anonymized_at' => now(),
            ])->save();
        });

        GymAuditLog::record($request, $user, 'lgpd.aluno_anonimizado', $student, ['reason' => $validated['reason']]);

        return response()->json(['data' => ['id' => $student->id, 'anonymized_at' => $student->anonymized_at?->toIso8601String()]]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function personalData(GymStudent $student): array
    {
        $student->load(['guardians', 'biometricConsents', 'subscriptions.plan', 'invoices', 'unit']);

        return [
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'cpf' => $student->cpf,
                'email' => $student->email,
                'phone' => $student->phone,
                'birth_date' => $student->birth_date?->toDateString(),
                'status' => $student->status,
                'unit' => $student->unit->name,
                'face_status' => $student->face_status,
                'created_at' => $student->created_at?->toIso8601String(),
            ],
            'guardians' => $student->guardians->map(fn (GymGuardian $guardian) => [
                'name' => $guardian->name,
                'cpf' => $guardian->cpf,
                'relationship' => $guardian->relationship,
                'phone' => $guardian->phone,
                'email' => $guardian->email,
            ])->all(),
            'biometric_consents' => $student->biometricConsents->map(fn (GymBiometricConsent $consent) => [
                'granted_by' => $consent->granted_by,
                'terms_version' => $consent->terms_version,
                'granted_at' => $consent->granted_at->toIso8601String(),
                'revoked_at' => $consent->revoked_at?->toIso8601String(),
            ])->all(),
            'subscriptions' => $student->subscriptions->map(fn ($subscription) => [
                'plan' => $subscription->plan->name,
                'price' => $subscription->price,
                'status' => $subscription->status,
                'starts_on' => $subscription->starts_on->toDateString(),
            ])->all(),
            'invoices' => $student->invoices->map(fn (GymInvoice $invoice) => [
                'amount' => $invoice->amount,
                'due_on' => $invoice->due_on->toDateString(),
                'status' => $invoice->displayStatus(),
            ])->all(),
            'access_events' => $student->accessEvents()->latest('occurred_at')->limit(500)->get()
                ->map(fn (GymAccessEvent $event) => [
                    'occurred_at' => $event->occurred_at->toIso8601String(),
                    'direction' => $event->direction,
                    'method' => $event->method,
                    'decision' => $event->decision,
                ])->all(),
            'stored_biometric_data' => 'Nenhuma imagem ou template facial é armazenado nos servidores; o template fica apenas no terminal da unidade.',
        ];
    }
}
