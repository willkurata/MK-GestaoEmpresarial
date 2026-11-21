<?php

namespace App\Http\Controllers;

use App\Actions\Companies\AssessCompanyRisk;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Refund;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const BUSINESS_TIMEZONE = 'America/Sao_Paulo';

    private const PERIODS = ['today', 'last_7_days', 'this_month', 'last_month', 'custom'];

    private const MIN_TREND_DAYS = 7;

    private const MAX_DAILY_TREND_DAYS = 92;

    private const MONTH_LABELS = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

    public function __invoke(Request $request, AssessCompanyRisk $assessRisk): Response
    {
        /** @var Company $company */
        $company = $request->user('company');
        $filters = $request->validate([
            'period' => ['nullable', Rule::in(self::PERIODS)],
            'start_date' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d'],
            'end_date' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        $period = $filters['period'] ?? 'today';
        $today = CarbonImmutable::now(self::BUSINESS_TIMEZONE)->startOfDay();
        [$startDate, $endDate] = match ($period) {
            'last_7_days' => [$today->subDays(6), $today],
            'this_month' => [$today->startOfMonth(), $today->endOfMonth()],
            'last_month' => [$today->subMonth()->startOfMonth(), $today->subMonth()->endOfMonth()],
            'custom' => [
                CarbonImmutable::createFromFormat('!Y-m-d', $filters['start_date'], self::BUSINESS_TIMEZONE),
                CarbonImmutable::createFromFormat('!Y-m-d', $filters['end_date'], self::BUSINESS_TIMEZONE),
            ],
            default => [$today, $today],
        };

        $databaseTimezone = config('app.timezone', 'UTC');
        $queryStart = $startDate->setTimezone($databaseTimezone);
        $queryEnd = $endDate->endOfDay()->setTimezone($databaseTimezone);
        $sales = $company->sales()
            ->where('status', 'finalizada')
            ->whereBetween('occurred_at', [$queryStart, $queryEnd]);
        $expenses = $company->expenses()
            ->whereBetween('expense_date', [$startDate->toDateString(), $endDate->toDateString()]);

        $paymentTotals = Payment::query()
            ->where('company_id', $company->id)
            ->where('status', 'confirmado')
            ->whereHas('sale', fn (Builder $query) => $query
                ->where('status', 'finalizada')
                ->whereBetween('occurred_at', [$queryStart, $queryEnd]))
            ->selectRaw('LOWER(method) as method, SUM(amount) as total')
            ->groupByRaw('LOWER(method)')
            ->pluck('total', 'method');

        $expenseTotals = (clone $expenses)
            ->selectRaw('type, SUM(amount) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $recentSales = (clone $sales)
            ->with('payment')
            ->latest('occurred_at')
            ->limit(8)
            ->get()
            ->map(fn ($sale): array => [
                'id' => 'sale-'.$sale->id,
                'type' => 'sale',
                'title' => 'Venda #'.$sale->id.' · '.($sale->payment?->method ?? 'Pagamento'),
                'date' => $sale->occurred_at->setTimezone(self::BUSINESS_TIMEZONE)->format('d/m/Y H:i'),
                'sortAt' => $sale->occurred_at->format('Y-m-d H:i:s'),
                'amount' => (string) $sale->total,
            ]);

        $recentExpenses = (clone $expenses)
            ->latest('expense_date')
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn ($expense): array => [
                'id' => 'expense-'.$expense->id,
                'type' => 'expense',
                'title' => $expense->description,
                'date' => $expense->expense_date->format('d/m/Y'),
                'sortAt' => $expense->expense_date->format('Y-m-d'),
                'amount' => (string) $expense->amount,
            ]);

        $recentActivities = $recentSales->concat($recentExpenses)
            ->sortByDesc('sortAt')
            ->take(8)
            ->map(fn (array $activity): array => collect($activity)->except('sortAt')->all())
            ->values();

        $salesTotal = (float) (clone $sales)->sum('total');
        $expenseTotal = (float) (clone $expenses)->sum('amount');
        $inflow = (float) $paymentTotals->get('dinheiro', 0) + (float) $paymentTotals->get('pix', 0);
        $refundTotal = (float) Refund::query()
            ->where('company_id', $company->id)
            ->whereBetween('refunded_at', [$queryStart, $queryEnd])
            ->sum('amount');

        $periodLengthInDays = (int) $startDate->diffInDays($endDate) + 1;
        $previousEnd = $startDate->subDay();
        $previousStart = $previousEnd->subDays($periodLengthInDays - 1);
        $previousSalesTotal = (float) $company->sales()
            ->where('status', 'finalizada')
            ->whereBetween('occurred_at', [
                $previousStart->setTimezone($databaseTimezone),
                $previousEnd->endOfDay()->setTimezone($databaseTimezone),
            ])
            ->sum('total');

        return Inertia::render('Dashboard', [
            'company' => $company->only(['name']),
            'companyUser' => $request->user('company_user')->only(['name', 'role']),
            'filters' => [
                'period' => $period,
                'startDate' => $startDate->toDateString(),
                'endDate' => $endDate->toDateString(),
            ],
            'summary' => [
                'salesCount' => (clone $sales)->count(),
                'salesTotal' => $this->money($salesTotal),
                'cashTotal' => $this->money($paymentTotals->get('dinheiro', 0)),
                'pixTotal' => $this->money($paymentTotals->get('pix', 0)),
                'expenseCount' => (clone $expenses)->count(),
                'expenseTotal' => $this->money($expenseTotal),
                'fixedExpenseTotal' => $this->money($expenseTotals->get('fixa', 0)),
                'variableExpenseTotal' => $this->money($expenseTotals->get('variavel', 0)),
                'refundTotal' => $this->money($refundTotal),
                'netInflow' => $this->money($inflow - $refundTotal),
                'previousSalesTotal' => $this->money($previousSalesTotal),
                'salesVariation' => $previousSalesTotal > 0
                    ? round(($salesTotal - $previousSalesTotal) / $previousSalesTotal * 100, 1)
                    : null,
            ],
            'team' => [
                'activeUsersCount' => $company->companyUsers()->where('status', 'ativa')->count(),
                'openSessionCount' => $company->cashSessions()->where('status', 'aberto')->count(),
            ],
            'risk' => $assessRisk->handle($salesTotal, $expenseTotal, $previousSalesTotal),
            'trend' => $this->trend($company, $startDate, $endDate),
            'recentActivities' => $recentActivities,
        ]);
    }

    /**
     * Série de vendas e despesas para o gráfico de tendência.
     *
     * Não projeta dias futuros (o mês atual termina hoje), usa no mínimo 7 dias
     * terminando na data final (para "Hoje" não virar um único ponto) e agrupa por mês quando o intervalo passa de 92 dias. O
     * agrupamento é feito em PHP no horário de Brasília para funcionar igual em
     * MySQL e SQLite.
     *
     * @return array{granularity: string, points: list<array{key: string, label: string, sales: string, expenses: string}>}
     */
    private function trend(Company $company, CarbonImmutable $startDate, CarbonImmutable $endDate): array
    {
        $today = CarbonImmutable::now(self::BUSINESS_TIMEZONE)->startOfDay();
        $endDate = $endDate->startOfDay()->min($today)->max($startDate->startOfDay());
        $trendStart = $startDate->diffInDays($endDate) + 1 < self::MIN_TREND_DAYS
            ? $endDate->subDays(self::MIN_TREND_DAYS - 1)
            : $startDate;
        $isMonthly = $trendStart->diffInDays($endDate) + 1 > self::MAX_DAILY_TREND_DAYS;
        $bucketFormat = $isMonthly ? 'Y-m' : 'Y-m-d';

        $points = [];
        $cursor = $isMonthly ? $trendStart->startOfMonth() : $trendStart;

        while ($cursor->lessThanOrEqualTo($endDate)) {
            $points[$cursor->format($bucketFormat)] = [
                'key' => $cursor->format($bucketFormat),
                'label' => $isMonthly
                    ? self::MONTH_LABELS[$cursor->month - 1].'/'.$cursor->format('y')
                    : $cursor->format('d/m'),
                'sales' => 0.0,
                'expenses' => 0.0,
            ];
            $cursor = $isMonthly ? $cursor->addMonthNoOverflow() : $cursor->addDay();
        }

        $databaseTimezone = config('app.timezone', 'UTC');
        $company->sales()
            ->where('status', 'finalizada')
            ->whereBetween('occurred_at', [
                $trendStart->setTimezone($databaseTimezone),
                $endDate->endOfDay()->setTimezone($databaseTimezone),
            ])
            ->get(['occurred_at', 'total'])
            ->each(function ($sale) use (&$points, $bucketFormat): void {
                $key = $sale->occurred_at->setTimezone(self::BUSINESS_TIMEZONE)->format($bucketFormat);

                if (isset($points[$key])) {
                    $points[$key]['sales'] += (float) $sale->total;
                }
            });

        $company->expenses()
            ->whereBetween('expense_date', [$trendStart->toDateString(), $endDate->toDateString()])
            ->get(['expense_date', 'amount'])
            ->each(function ($expense) use (&$points, $bucketFormat): void {
                $key = $expense->expense_date->format($bucketFormat);

                if (isset($points[$key])) {
                    $points[$key]['expenses'] += (float) $expense->amount;
                }
            });

        return [
            'granularity' => $isMonthly ? 'month' : 'day',
            'points' => array_values(array_map(fn (array $point): array => [
                ...$point,
                'sales' => $this->money($point['sales']),
                'expenses' => $this->money($point['expenses']),
            ], $points)),
        ];
    }

    private function money(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
