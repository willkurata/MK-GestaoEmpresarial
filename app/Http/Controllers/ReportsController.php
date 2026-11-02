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

class ReportsController extends Controller
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
            ->whereIn('status', ['finalizada', 'estornada'])
            ->whereBetween('occurred_at', [$queryStart, $queryEnd]);
        $finalizedSales = (clone $sales)->where('status', 'finalizada');
        $refundedSales = (clone $sales)->where('status', 'estornada');

        $paymentTotals = Payment::query()
            ->where('company_id', $company->id)
            ->where('status', 'confirmado')
            ->whereHas('sale', fn (Builder $query) => $query
                ->where('status', 'finalizada')
                ->whereBetween('occurred_at', [$queryStart, $queryEnd]))
            ->selectRaw('LOWER(method) as method, SUM(amount) as total')
            ->groupByRaw('LOWER(method)')
            ->pluck('total', 'method');

        $cashSessions = $company->cashSessions()
            ->whereBetween('opened_at', [$queryStart, $queryEnd]);
        $closedCashSessions = (clone $cashSessions)->where('status', 'fechado');

        $recentSales = (clone $sales)
            ->with(['payment', 'user:id,name'])
            ->latest('occurred_at')
            ->limit(25)
            ->get()
            ->map(fn ($sale): array => [
                'id' => $sale->id,
                'occurredAt' => $sale->occurred_at->setTimezone(self::BUSINESS_TIMEZONE)->format('d/m/Y H:i'),
                'operator' => $sale->user?->name ?? '—',
                'paymentMethod' => $sale->payment?->method ?? '—',
                'total' => $this->money($sale->total),
                'status' => $sale->status,
            ]);

        $recentCashSessions = (clone $cashSessions)
            ->with('user:id,name')
            ->latest('opened_at')
            ->limit(25)
            ->get()
            ->map(fn ($session): array => [
                'id' => $session->id,
                'operator' => $session->user?->name ?? '—',
                'openedAt' => $session->opened_at->setTimezone(self::BUSINESS_TIMEZONE)->format('d/m/Y H:i'),
                'closedAt' => $session->closed_at?->setTimezone(self::BUSINESS_TIMEZONE)->format('d/m/Y H:i'),
                'openingAmount' => $this->money($session->opening_amount),
                'expectedAmount' => $session->expected_amount === null ? null : $this->money($session->expected_amount),
                'countedAmount' => $session->counted_amount === null ? null : $this->money($session->counted_amount),
                'differenceAmount' => $session->difference_amount === null ? null : $this->money($session->difference_amount),
                'status' => $session->status,
            ]);

        return Inertia::render('Reports/Index', [
            'company' => $company->only(['name']),
            'filters' => [
                'period' => $period,
                'startDate' => $startDate->toDateString(),
                'endDate' => $endDate->toDateString(),
            ],
            'summary' => [
                'salesCount' => (clone $finalizedSales)->count(),
                'salesTotal' => $this->money((clone $finalizedSales)->sum('total')),
                'cashTotal' => $this->money($paymentTotals->get('dinheiro', 0)),
                'pixTotal' => $this->money($paymentTotals->get('pix', 0)),
                'refundedCount' => (clone $refundedSales)->count(),
                'refundedTotal' => $this->money((clone $refundedSales)->sum('total')),
            ],
            'cashSummary' => [
                'sessionCount' => (clone $cashSessions)->count(),
                'closedCount' => (clone $closedCashSessions)->count(),
                'openCount' => (clone $cashSessions)->where('status', 'aberto')->count(),
                'expectedTotal' => $this->money((clone $closedCashSessions)->sum('expected_amount')),
                'countedTotal' => $this->money((clone $closedCashSessions)->sum('counted_amount')),
                'differenceTotal' => $this->money((clone $closedCashSessions)->sum('difference_amount')),
            ],
            'recentSales' => $recentSales,
            'recentCashSessions' => $recentCashSessions,
        ]);
    }

    private function money(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
