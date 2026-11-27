<?php

namespace App\Http\Controllers\Api\Gym;

use App\Http\Controllers\Controller;
use App\Http\Resources\Gym\GymStudentResource;
use App\Models\Gym\GymAuditLog;
use App\Models\Gym\GymBiometricConsent;
use App\Models\Gym\GymStudent;
use App\Models\Gym\GymUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Facial biometrics under LGPD art. 11: explicit consent first, template stays on the terminal,
 * the cloud only keeps the enrolment status. Face images or templates are never accepted here.
 */
class BiometricController extends Controller
{
    public function grantConsent(Request $request, int $student): JsonResponse
    {
        /** @var GymUser $user */
        $user = $request->user();
        $student = $this->findStudent($user, $student);
        $validated = $request->validate([
            'granted_by' => ['required', Rule::in([GymBiometricConsent::GRANTED_BY_HOLDER, GymBiometricConsent::GRANTED_BY_GUARDIAN])],
            'guardian_id' => ['nullable', 'required_if:granted_by,responsavel', 'integer', Rule::exists('gym_guardians', 'id')->where('gym_student_id', $student->id)],
            'terms_version' => ['required', 'string', Rule::in([config('academia.biometric_terms_version')])],
            'accepted' => ['accepted'],
        ], [
            'terms_version.in' => 'Os termos de biometria foram atualizados. Apresente a versão vigente ao aluno.',
            'accepted.accepted' => 'O consentimento específico para biometria precisa ser aceito.',
        ]);

        if ($student->isMinor() && $validated['granted_by'] !== GymBiometricConsent::GRANTED_BY_GUARDIAN) {
            throw ValidationException::withMessages(['granted_by' => 'Para menores de 18 anos o consentimento deve ser dado pelo responsável legal.']);
        }

        if ($student->activeBiometricConsent()->exists()) {
            throw ValidationException::withMessages(['student' => 'O aluno já possui consentimento de biometria ativo.']);
        }

        $consent = $student->biometricConsents()->create([
            'gym_id' => $student->gym_id,
            'gym_guardian_id' => $validated['guardian_id'] ?? null,
            'granted_by' => $validated['granted_by'],
            'terms_version' => $validated['terms_version'],
            'recorded_by_user_id' => $user->id,
            'ip_address' => $request->ip(),
            'granted_at' => now(),
        ]);
        GymAuditLog::record($request, $user, 'biometria.consentimento_concedido', $student, ['consent_id' => $consent->id]);

        return response()->json(['data' => [
            'id' => $consent->id,
            'granted_by' => $consent->granted_by,
            'terms_version' => $consent->terms_version,
            'granted_at' => $consent->granted_at->toIso8601String(),
        ]], 201);
    }

    public function revokeConsent(Request $request, int $student): GymStudentResource
    {
        /** @var GymUser $user */
        $user = $request->user();
        $student = $this->findStudent($user, $student);
        self::revokeFor($student);
        GymAuditLog::record($request, $user, 'biometria.consentimento_revogado', $student);

        return GymStudentResource::make($student->refresh()->load('activeBiometricConsent'));
    }

    /**
     * Records that the terminal enrolled the face. Only the status travels; images/templates are refused.
     */
    public function enrollFace(Request $request, int $student): GymStudentResource
    {
        /** @var GymUser $user */
        $user = $request->user();
        $student = $this->findStudent($user, $student);
        $validated = $request->validate([
            'device_id' => ['required', 'integer', Rule::exists('gym_access_devices', 'id')->where('gym_id', $user->gym_id)],
            'photo' => ['prohibited'],
            'image' => ['prohibited'],
            'template' => ['prohibited'],
        ], [
            'photo.prohibited' => 'Imagens faciais não são armazenadas na nuvem.',
            'image.prohibited' => 'Imagens faciais não são armazenadas na nuvem.',
            'template.prohibited' => 'Templates biométricos ficam apenas no terminal.',
        ]);

        if (! $student->activeBiometricConsent()->exists()) {
            throw ValidationException::withMessages(['student' => 'Registre o consentimento de biometria antes do cadastro facial.']);
        }

        $student->forceFill(['face_status' => GymStudent::FACE_ENROLLED, 'face_enrolled_at' => now()])->save();
        GymAuditLog::record($request, $user, 'biometria.face_cadastrada', $student, [
            'device_id' => (int) $validated['device_id'],
        ]);

        return GymStudentResource::make($student->load('activeBiometricConsent'));
    }

    public function removeFace(Request $request, int $student): GymStudentResource
    {
        /** @var GymUser $user */
        $user = $request->user();
        $student = $this->findStudent($user, $student);
        $student->forceFill(['face_status' => GymStudent::FACE_NOT_ENROLLED, 'face_enrolled_at' => null])->save();
        GymAuditLog::record($request, $user, 'biometria.face_removida', $student);

        return GymStudentResource::make($student->load('activeBiometricConsent'));
    }

    /**
     * Revokes every active consent and clears the enrolment so terminals drop the template on next sync.
     */
    public static function revokeFor(GymStudent $student): void
    {
        DB::transaction(function () use ($student): void {
            $student->biometricConsents()->whereNull('revoked_at')->update(['revoked_at' => now()]);
            $student->forceFill(['face_status' => GymStudent::FACE_NOT_ENROLLED, 'face_enrolled_at' => null])->save();
        });
    }

    private function findStudent(GymUser $user, int $studentId): GymStudent
    {
        return $user->visibleStudents()->whereNull('anonymized_at')->findOrFail($studentId);
    }
}
