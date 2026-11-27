<?php

namespace App\Actions\Gym;

use App\Models\Gym\GymInvoice;
use App\Models\Gym\GymStudent;
use App\Models\Gym\GymSubscription;

/**
 * Turnstile rule: who may pass, decided from registration, biometric consent and financial status.
 */
class DecideStudentAccess
{
    /**
     * @return array{allowed: bool, reason: string|null}
     */
    public function handle(?GymStudent $student, string $direction, string $method): array
    {
        if ($student === null || $student->anonymized_at !== null) {
            return self::deny('aluno_nao_encontrado');
        }

        if ($direction === 'saida') {
            return ['allowed' => true, 'reason' => null];
        }

        if ($method === 'facial' && ! $this->hasFacialAuthorization($student)) {
            return self::deny('biometria_nao_autorizada');
        }

        if ($student->status !== 'ativo') {
            return self::deny('aluno_inativo');
        }

        /** @var GymSubscription|null $subscription */
        $subscription = $student->subscriptions()->with('plan')->where('status', 'ativa')->latest('id')->first();

        if ($subscription === null) {
            return self::deny('sem_plano_ativo');
        }

        $graceDays = $subscription->plan->grace_days;
        $hasOverdueInvoice = $student->invoices()
            ->where('status', GymInvoice::STATUS_PENDING)
            ->whereDate('due_on', '<', today()->subDays($graceDays))
            ->exists();

        if ($hasOverdueInvoice) {
            return self::deny('mensalidade_em_atraso');
        }

        return ['allowed' => true, 'reason' => null];
    }

    public function hasFacialAuthorization(GymStudent $student): bool
    {
        return $student->face_status === GymStudent::FACE_ENROLLED
            && $student->activeBiometricConsent()->exists();
    }

    /**
     * @return array{allowed: false, reason: string}
     */
    private static function deny(string $reason): array
    {
        return ['allowed' => false, 'reason' => $reason];
    }
}
