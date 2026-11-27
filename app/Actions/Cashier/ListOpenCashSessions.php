<?php

namespace App\Actions\Cashier;

use App\Models\CashSession;
use App\Models\Company;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lista os turnos de caixa ainda abertos (não fechados), com a empresa, o
 * operador responsável e há quanto tempo estão abertos. Somente leitura.
 *
 * Um turno é considerado "esquecido" quando foi aberto antes do início do dia
 * atual no fuso da operação: o turno do dia anterior não foi fechado.
 */
class ListOpenCashSessions
{
    private const BUSINESS_TIMEZONE = 'America/Sao_Paulo';

    /**
     * Turnos abertos de uma única empresa (isolamento por empresa).
     *
     * @return list<array{id: int, company: array{id: int, name: string, cnpj: string}, operator: string, register: string, openedAt: string, openedAtIso: string, hoursOpen: int, isStale: bool}>
     */
    public function forCompany(Company $company): array
    {
        return $this->describe(
            CashSession::query()->where('company_id', $company->id),
        );
    }

    /**
     * Turnos abertos de todas as empresas, para o painel MK.
     *
     * @return list<array{id: int, company: array{id: int, name: string, cnpj: string}, operator: string, register: string, openedAt: string, openedAtIso: string, hoursOpen: int, isStale: bool}>
     */
    public function all(): array
    {
        return $this->describe(CashSession::query());
    }

    /**
     * @param  Builder<CashSession>  $query
     * @return list<array{id: int, company: array{id: int, name: string, cnpj: string}, operator: string, register: string, openedAt: string, openedAtIso: string, hoursOpen: int, isStale: bool}>
     */
    private function describe(Builder $query): array
    {
        $now = CarbonImmutable::now(self::BUSINESS_TIMEZONE);
        $startOfToday = $now->startOfDay();

        return $query
            ->where('status', 'aberto')
            ->with(['company:id,name,cnpj', 'user:id,name', 'cashRegister:id,name'])
            ->oldest('opened_at')
            ->get()
            ->map(function (CashSession $session) use ($now, $startOfToday): array {
                $openedAt = CarbonImmutable::instance($session->opened_at)->setTimezone(self::BUSINESS_TIMEZONE);

                return [
                    'id' => $session->id,
                    'company' => [
                        'id' => $session->company->id,
                        'name' => $session->company->name,
                        'cnpj' => $session->company->cnpj,
                    ],
                    'operator' => $session->user?->name ?? 'Operador desconhecido',
                    'register' => $session->cashRegister?->name ?? 'Caixa',
                    'openedAt' => $openedAt->format('d/m/Y H:i'),
                    'openedAtIso' => $openedAt->toIso8601String(),
                    'hoursOpen' => (int) max(0, $openedAt->diffInHours($now)),
                    'isStale' => $openedAt->lessThan($startOfToday),
                ];
            })
            ->values()
            ->all();
    }
}
