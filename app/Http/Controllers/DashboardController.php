<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Payment;
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

    public function __invoke(Request $request): Response
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
                'salesTotal' => $this->money((clone $sales)->sum('total')),
                'cashTotal' => $this->money($paymentTotals->get('dinheiro', 0)),
                'pixTotal' => $this->money($paymentTotals->get('pix', 0)),
                'expenseCount' => (clone $expenses)->count(),
                'expenseTotal' => $this->money((clone $expenses)->sum('amount')),
                'fixedExpenseTotal' => $this->money($expenseTotals->get('fixa', 0)),
                'variableExpenseTotal' => $this->money($expenseTotals->get('variavel', 0)),
            ],
            'recentActivities' => $recentActivities,
        ]);
    }

    private function money(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
