<?php

namespace App\Http\Controllers;

use App\Actions\Cashier\ResolvePreviousDayCashSession;
use App\Models\CashSession;
use App\Models\Company;
use App\Models\CompanyUser;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Shifts left open from a previous business day (WIL-42): the decision taken at the
 * cashier and the report of shifts that crossed the day.
 */
class CashSessionCarryOverController extends Controller
{
    private const MAX_REPORT_DAYS = 366;

    public function index(Request $request): Response
    {
        /** @var Company $company */
        $company = $request->user('company');
        $timezone = CashSession::BUSINESS_TIMEZONE;
        $today = Carbon::now($timezone)->startOfDay();
        $validated = $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);
        $start = isset($validated['start_date'])
            ? Carbon::createFromFormat('Y-m-d', $validated['start_date'], $timezone)->startOfDay()
            : $today->copy()->subDays(29);
        $end = isset($validated['end_date'])
            ? Carbon::createFromFormat('Y-m-d', $validated['end_date'], $timezone)->endOfDay()
            : $today->copy()->endOfDay();
        if ($start->diffInDays($end) > self::MAX_REPORT_DAYS) {
            $start = $end->copy()->subDays(self::MAX_REPORT_DAYS)->startOfDay();
        }

        $pendingSessions = CashSession::query()->whereBelongsTo($company)
            ->where('status', 'aberto')
            ->where('opened_at', '<', $today->copy()->utc())
            ->with('user:id,name')
            ->orderBy('opened_at')
            ->get()
            ->map(fn (CashSession $session): array => [
                'id' => $session->id,
                'operator' => $session->user->name,
                'openedAt' => $this->formatMoment($session->opened_at),
                'daysOpen' => $session->daysOpen(),
                'openingAmount' => (string) $session->opening_amount,
                'expectedCash' => CashSession::fromCents($session->expectedCashCents()),
                'pixTotal' => CashSession::fromCents($session->pixTotalCents()),
                'continuedAt' => $session->continued_at ? $this->formatMoment($session->continued_at) : null,
            ]);

        $history = CashSession::query()->whereBelongsTo($company)
            ->where('status', 'fechado')
            ->whereBetween('opened_at', [$start->copy()->utc(), $end->copy()->utc()])
            ->with(['user:id,name', 'closedBy:id,name', 'nextSession.user:id,name'])
            ->latest('opened_at')
            ->limit(500)
            ->get()
            ->filter(fn (CashSession $session): bool => $session->daysOpen() > 0
                || $session->closing_mode === CashSession::CLOSING_AUTOMATIC
                || $session->continued_at !== null
                || $session->nextSession !== null)
            ->values()
            ->map(function (CashSession $session): array {
                $next = $session->nextSession;
                $transferDifference = $next && $next->transferred_amount !== null
                    ? CashSession::toCents((string) $next->opening_amount) - CashSession::toCents((string) $next->transferred_amount)
                    : null;

                return [
                    'id' => $session->id,
                    'operator' => $session->user->name,
                    'openedAt' => $this->formatMoment($session->opened_at),
                    'closedAt' => $session->closed_at ? $this->formatMoment($session->closed_at) : null,
                    'daysOpen' => $session->daysOpen(),
                    'closingMode' => $session->closing_mode,
                    'closedBy' => $session->closedBy?->name,
                    'continuedAt' => $session->continued_at ? $this->formatMoment($session->continued_at) : null,
                    'expectedAmount' => $session->expected_amount === null ? null : (string) $session->expected_amount,
                    'countedAmount' => $session->counted_amount === null ? null : (string) $session->counted_amount,
                    'differenceAmount' => $session->difference_amount === null ? null : (string) $session->difference_amount,
                    'transfer' => $next ? [
                        'nextSessionId' => $next->id,
                        'operator' => $next->user->name,
                        'transferredAmount' => (string) $next->transferred_amount,
                        'openingAmount' => (string) $next->opening_amount,
                        'difference' => $transferDifference === null ? null : CashSession::fromCents($transferDifference),
                    ] : null,
                ];
            });

        return Inertia::render('Cashier/PreviousDays', [
            'filters' => ['startDate' => $start->toDateString(), 'endDate' => $end->toDateString()],
            'pendingSessions' => $pendingSessions,
            'history' => $history,
            'summary' => [
                'pendingCount' => $pendingSessions->count(),
                'pendingExpectedCash' => CashSession::fromCents($pendingSessions->sum(fn (array $row): int => CashSession::toCents($row['expectedCash']))),
                'crossedDayCount' => $history->where('daysOpen', '>', 0)->count(),
                'autoClosedCount' => $history->where('closingMode', CashSession::CLOSING_AUTOMATIC)->count(),
                'transferCount' => $history->whereNotNull('transfer')->count(),
                'transferDifferenceTotal' => CashSession::fromCents($history->sum(
                    fn (array $row): int => CashSession::toCents($row['transfer']['difference'] ?? '0'),
                )),
            ],
        ]);
    }

    public function store(Request $request, int $cashSession, ResolvePreviousDayCashSession $resolve): RedirectResponse
    {
        /** @var Company $company */
        $company = $request->user('company');
        /** @var CompanyUser $companyUser */
        $companyUser = $request->user('company_user');
        $validated = $request->validate([
            'action' => ['required', Rule::in(ResolvePreviousDayCashSession::ACTIONS)],
            'counted_cash' => ['required_if:action,close', 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'open_new_session' => ['boolean'],
            'opening_amount' => [
                Rule::requiredIf(fn (): bool => $request->input('action') === 'auto_close' && $request->boolean('open_new_session')),
                'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99',
            ],
        ]);
        $validated['open_new_session'] = $request->boolean('open_new_session');

        $resolve->handle($company, $companyUser, $cashSession, $validated);

        return to_route('pos.index')->with('success', match (true) {
            $validated['action'] === 'continue' => 'Você continuará no mesmo turno hoje.',
            $validated['open_new_session'] => 'Turno anterior fechado e novo turno aberto com o saldo transferido.',
            $validated['action'] === 'auto_close' => 'Turno anterior fechado automaticamente. Ele ficará pendente de conferência.',
            default => 'Turno anterior fechado.',
        });
    }

    private function formatMoment(CarbonInterface $moment): string
    {
        return $moment->copy()->setTimezone(CashSession::BUSINESS_TIMEZONE)->format('d/m/Y H:i');
    }
}
