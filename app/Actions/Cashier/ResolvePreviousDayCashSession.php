<?php

namespace App\Actions\Cashier;

use App\Models\AuditLog;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Company;
use App\Models\CompanyUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Decides what happens to a shift left open from a previous business day (WIL-42).
 *
 * - continue: the shift owner keeps working in the same shift today.
 * - close: the drawer is counted and the shift closed; the counted cash can be carried
 *   to a new shift as its opening amount.
 * - auto_close: the system closes the shift at the expected amount without a count and
 *   flags it for review; the new shift opens with the cash counted now and the gap stays
 *   visible as a transfer difference.
 */
class ResolvePreviousDayCashSession
{
    public const ACTIONS = ['continue', 'close', 'auto_close'];

    /**
     * @param  array{action: string, counted_cash?: string|int|float|null, opening_amount?: string|int|float|null, open_new_session?: bool}  $input
     */
    public function handle(Company $company, CompanyUser $actor, int $cashSessionId, array $input): CashSession
    {
        return DB::transaction(function () use ($company, $actor, $cashSessionId, $input): CashSession {
            $company->newQuery()->whereKey($company->id)->lockForUpdate()->firstOrFail();

            $session = CashSession::query()->whereBelongsTo($company)->whereKey($cashSessionId)->lockForUpdate()->firstOrFail();
            $isOwner = (int) $session->company_user_id === (int) $actor->id;

            abort_unless($isOwner || $actor->hasPermission('resolve-previous-day-shift'), 403, 'Somente o operador do turno, um Administrador ou um Moderador pode resolver este caixa.');
            abort_unless($session->needsCarryOverDecision(), 422, 'Este turno não está pendente de um dia anterior.');

            if ($input['action'] === 'continue') {
                abort_unless($isOwner, 403, 'Somente o operador que abriu o turno pode continuar nele.');
                $session->update(['continued_at' => now()]);
                AuditLog::record('cash_session.continued', $actor, $company->id, $session, [
                    'opened_at' => $session->opened_at->toIso8601String(),
                ]);

                return $session;
            }

            $expectedCents = $session->expectedCashCents();
            $isCounted = $input['action'] === 'close';
            $countedCents = $isCounted ? CashSession::toCents((string) ($input['counted_cash'] ?? '0')) : null;

            $session->update([
                'status' => 'fechado',
                'closing_mode' => $isCounted ? CashSession::CLOSING_COUNTED : CashSession::CLOSING_AUTOMATIC,
                'closed_at' => now(),
                'closed_by_company_user_id' => $actor->id,
                'expected_amount' => CashSession::fromCents($expectedCents),
                'counted_amount' => $countedCents === null ? null : CashSession::fromCents($countedCents),
                'difference_amount' => $countedCents === null ? null : CashSession::fromCents($countedCents - $expectedCents),
            ]);

            $newSession = null;
            if ($input['open_new_session'] ?? false) {
                $transferredCents = $countedCents ?? $expectedCents;
                $openingCents = $isCounted ? $countedCents : CashSession::toCents((string) ($input['opening_amount'] ?? '0'));
                $newSession = $this->openCarriedOverSession($company, $actor, $session, $transferredCents, (int) $openingCents);
            }

            AuditLog::record(
                $isCounted ? 'cash_session.closed_next_day' : 'cash_session.auto_closed',
                $actor,
                $company->id,
                $session,
                array_filter([
                    'expected_amount' => CashSession::fromCents($expectedCents),
                    'counted_amount' => $countedCents === null ? null : CashSession::fromCents($countedCents),
                    'new_cash_session_id' => $newSession?->id,
                    'transferred_amount' => $newSession?->transferred_amount,
                    'opening_amount' => $newSession?->opening_amount,
                ], fn ($value): bool => $value !== null),
            );

            return $newSession ?? $session;
        });
    }

    private function openCarriedOverSession(Company $company, CompanyUser $actor, CashSession $previous, int $transferredCents, int $openingCents): CashSession
    {
        if ($openingCents < 0) {
            throw ValidationException::withMessages(['opening_amount' => 'O valor de abertura não pode ser negativo.']);
        }

        $company->companyUsers()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
        abort_if($company->cashSessions()->where('status', 'aberto')->exists(), 422, 'Já existe um caixa aberto para esta empresa.');

        $register = CashRegister::query()->whereBelongsTo($company)->whereKey($previous->cash_register_id)->lockForUpdate()->firstOrFail();
        abort_if($register->status !== 'ativo', 422, 'O caixa permanente desta empresa está inativo.');
        abort_if($register->company_user_id && (int) $register->company_user_id !== (int) $actor->id, 403, 'Este caixa está atribuído a outro usuário.');

        return CashSession::query()->create([
            'cash_register_id' => $register->id,
            'company_id' => $company->id,
            'company_user_id' => $actor->id,
            'opened_at' => now(),
            'opening_amount' => CashSession::fromCents($openingCents),
            'status' => 'aberto',
            'previous_cash_session_id' => $previous->id,
            'transferred_amount' => CashSession::fromCents($transferredCents),
        ]);
    }
}
