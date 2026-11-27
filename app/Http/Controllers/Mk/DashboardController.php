<?php

namespace App\Http\Controllers\Mk;

use App\Actions\Cashier\ListOpenCashSessions;
use App\Actions\Companies\AssessCompanyRisk;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Sale;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const BUSINESS_TIMEZONE = 'America/Sao_Paulo';

    private const MONTHS_IN_TREND = 6;

    private const MONTH_LABELS = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

    /**
     * Visão master: indicadores do mês atual (até hoje) por empresa, comparados
     * com o mesmo número de dias do mês anterior. Somente leitura.
     */
    public function __invoke(AssessCompanyRisk $assessRisk, ListOpenCashSessions $listOpenCashSessions): Response
    {
        $today = CarbonImmutable::now(self::BUSINESS_TIMEZONE)->startOfDay();
        $currentStart = $today->startOfMonth();
        $previousStart = $currentStart->subMonthNoOverflow();
        $previousEnd = $previousStart->addDays((int) $currentStart->diffInDays($today))->min($previousStart->endOfMonth()->startOfDay());

        $currentRange = $this->queryRange($currentStart, $today);
        $previousRange = $this->queryRange($previousStart, $previousEnd);
        $currentDates = [$currentStart->toDateString(), $today->toDateString()];

        $companies = Company::query()
            ->select(['id', 'cnpj', 'name', 'status', 'created_at'])
            ->withCount([
                'sales as sales_count' => fn (Builder $query) => $query->where('status', 'finalizada')->whereBetween('occurred_at', $currentRange),
                'companyUsers as active_users_count' => fn (Builder $query) => $query->where('status', 'ativa'),
                'companyUsers as users_count',
                'cashSessions as open_sessions_count' => fn (Builder $query) => $query->where('status', 'aberto'),
            ])
            ->withSum(['sales as revenue' => fn (Builder $query) => $query->where('status', 'finalizada')->whereBetween('occurred_at', $currentRange)], 'total')
            ->withSum(['sales as previous_revenue' => fn (Builder $query) => $query->where('status', 'finalizada')->whereBetween('occurred_at', $previousRange)], 'total')
            ->withSum(['expenses as expenses_total' => fn (Builder $query) => $query->whereBetween('expense_date', $currentDates)], 'amount')
            ->withSum(['cashSessions as cash_difference' => fn (Builder $query) => $query->where('status', 'fechado')->whereBetween('opened_at', $currentRange)], 'difference_amount')
            ->withMax(['sales as last_sale_at' => fn (Builder $query) => $query->where('status', 'finalizada')], 'occurred_at')
            ->orderBy('name')
            ->get()
            ->map(function (Company $company) use ($assessRisk): array {
                $revenue = (float) $company->getAttribute('revenue');
                $previousRevenue = (float) $company->getAttribute('previous_revenue');
                $expenses = (float) $company->getAttribute('expenses_total');
                $lastSaleAt = $company->getAttribute('last_sale_at');

                return [
                    'id' => $company->id,
                    'cnpj' => $company->cnpj,
                    'name' => $company->name,
                    'status' => $company->status,
                    'created_at' => $company->created_at,
                    'metrics' => [
                        'revenue' => $this->money($revenue),
                        'previousRevenue' => $this->money($previousRevenue),
                        'revenueVariation' => $previousRevenue > 0 ? round(($revenue - $previousRevenue) / $previousRevenue * 100, 1) : null,
                        'salesCount' => (int) $company->getAttribute('sales_count'),
                        'expenses' => $this->money($expenses),
                        'activeUsersCount' => (int) $company->getAttribute('active_users_count'),
                        'usersCount' => (int) $company->getAttribute('users_count'),
                        'openSessionsCount' => (int) $company->getAttribute('open_sessions_count'),
                        'lastSaleAt' => $lastSaleAt
                            ? CarbonImmutable::parse($lastSaleAt, config('app.timezone', 'UTC'))->setTimezone(self::BUSINESS_TIMEZONE)->format('d/m/Y H:i')
                            : null,
                    ],
                    'risk' => $company->status === 'ativa'
                        ? $assessRisk->handle($revenue, $expenses, $previousRevenue, (float) $company->getAttribute('cash_difference'))
                        : ['level' => 'none', 'label' => 'Inativa', 'reasons' => ['Empresa inativa: indicadores não são avaliados.']],
                ];
            });

        return Inertia::render('mk/Dashboard', [
            'period' => [
                'startDate' => $currentStart->toDateString(),
                'endDate' => $today->toDateString(),
                'previousStartDate' => $previousStart->toDateString(),
                'previousEndDate' => $previousEnd->toDateString(),
            ],
            'totals' => $this->totals($companies),
            'trend' => $this->monthlyTrend($today),
            'companies' => $companies,
            'openCashSessions' => $listOpenCashSessions->all(),
        ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $companies
     * @return array<string, mixed>
     */
    private function totals(Collection $companies): array
    {
        $revenue = $companies->sum(fn (array $company): float => (float) $company['metrics']['revenue']);
        $previousRevenue = $companies->sum(fn (array $company): float => (float) $company['metrics']['previousRevenue']);

        return [
            'companiesCount' => $companies->count(),
            'activeCompaniesCount' => $companies->where('status', 'ativa')->count(),
            'revenue' => $this->money($revenue),
            'revenueVariation' => $previousRevenue > 0 ? round(($revenue - $previousRevenue) / $previousRevenue * 100, 1) : null,
            'salesCount' => $companies->sum(fn (array $company): int => $company['metrics']['salesCount']),
            'expenses' => $this->money($companies->sum(fn (array $company): float => (float) $company['metrics']['expenses'])),
            'activeUsersCount' => $companies->sum(fn (array $company): int => $company['metrics']['activeUsersCount']),
            'openSessionsCount' => $companies->sum(fn (array $company): int => $company['metrics']['openSessionsCount']),
            'riskCounts' => collect(AssessCompanyRisk::LEVELS)
                ->mapWithKeys(fn (string $level): array => [$level => $companies->where('risk.level', $level)->count()])
                ->all(),
        ];
    }

    /**
     * Receita e despesas somadas de todas as empresas nos últimos meses.
     * Uma consulta por mês para continuar compatível com MySQL e SQLite.
     *
     * @return list<array{month: string, label: string, revenue: string, expenses: string}>
     */
    private function monthlyTrend(CarbonImmutable $today): array
    {
        $trend = [];
        $lastMonth = $today->startOfMonth();

        for ($offset = self::MONTHS_IN_TREND - 1; $offset >= 0; $offset--) {
            $monthStart = $lastMonth->subMonthsNoOverflow($offset);
            $monthEnd = $monthStart->endOfMonth()->startOfDay();

            $trend[] = [
                'month' => $monthStart->format('Y-m'),
                'label' => self::MONTH_LABELS[$monthStart->month - 1].'/'.$monthStart->format('y'),
                'revenue' => $this->money(Sale::query()
                    ->where('status', 'finalizada')
                    ->whereBetween('occurred_at', $this->queryRange($monthStart, $monthEnd))
                    ->sum('total')),
                'expenses' => $this->money(Expense::query()
                    ->whereBetween('expense_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                    ->sum('amount')),
            ];
        }

        return $trend;
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function queryRange(CarbonImmutable $startDate, CarbonImmutable $endDate): array
    {
        $databaseTimezone = config('app.timezone', 'UTC');

        return [
            $startDate->startOfDay()->setTimezone($databaseTimezone),
            $endDate->endOfDay()->setTimezone($databaseTimezone),
        ];
    }

    private function money(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
