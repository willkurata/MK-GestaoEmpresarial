<?php

namespace App\Http\Controllers\CyberCoffe;

use App\Models\CyberCoffe\Payment;
use App\Models\CyberCoffe\StationSession;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Reports are computed on demand from sessions and payments; nothing is stored.
 */
class ReportController extends CyberCoffeController
{
    /**
     * Revenue by method and purpose. Payments made with prepaid credits are
     * reported separately because the money was counted when credits were sold.
     */
    public function revenue(Request $request): JsonResponse
    {
        [$from, $to] = $this->period($request);

        $payments = $this->currentStore($request)->payments()
            ->where('paid_at', '>=', $from)
            ->where('paid_at', '<', $to)
            ->get(['method', 'purpose', 'amount']);

        $revenuePayments = $payments->whereIn('method', Payment::REVENUE_METHODS);
        $byMethod = [];

        foreach (Payment::REVENUE_METHODS as $method) {
            $byMethod[$method] = $this->sumInCents($revenuePayments->where('method', $method));
        }

        return response()->json([
            'period' => ['from' => $from->toDateString(), 'to' => $to->subDay()->toDateString()],
            'revenue_total' => $this->money(array_sum($byMethod)),
            'payments_count' => $revenuePayments->count(),
            'by_method' => array_map(fn (int $cents): string => $this->money($cents), $byMethod),
            'by_purpose' => [
                'sessao' => $this->money($this->sumInCents($revenuePayments->where('purpose', 'sessao'))),
                'creditos' => $this->money($this->sumInCents($revenuePayments->where('purpose', 'creditos'))),
            ],
            'credits_consumed' => $this->money($this->sumInCents($payments->where('method', 'creditos'))),
        ]);
    }

    /**
     * Occupied minutes per station inside the period, clipped to the period
     * and to the current time.
     */
    public function occupancy(Request $request): JsonResponse
    {
        [$from, $to] = $this->period($request);
        $store = $this->currentStore($request);
        $now = now();
        $windowEnd = $to->lessThan($now) ? $to : $now;
        $periodMinutes = max(0, intdiv((int) $from->diffInSeconds($windowEnd, false), 60));

        $sessions = $store->stationSessions()
            ->where('started_at', '<', $windowEnd)
            ->where(fn ($query) => $query->whereNull('closed_at')->orWhere('closed_at', '>', $from))
            ->get(['station_id', 'started_at', 'closed_at']);

        $stations = $store->stations()->orderBy('name')->get(['id', 'name', 'type'])->map(function ($station) use ($sessions, $from, $windowEnd, $periodMinutes): array {
            $stationSessions = $sessions->where('station_id', $station->id);
            $occupiedSeconds = $stationSessions->sum(fn (StationSession $session): int => $this->overlapSeconds($session, $from, $windowEnd));
            $occupiedMinutes = intdiv($occupiedSeconds, 60);

            return [
                'station_id' => $station->id,
                'name' => $station->name,
                'type' => $station->type,
                'sessions_count' => $stationSessions->count(),
                'occupied_minutes' => $occupiedMinutes,
                'occupancy_rate' => $periodMinutes > 0 ? round($occupiedMinutes / $periodMinutes, 4) : 0,
            ];
        });

        $totalOccupied = $stations->sum('occupied_minutes');
        $capacity = $periodMinutes * $stations->count();

        return response()->json([
            'period' => ['from' => $from->toDateString(), 'to' => $to->subDay()->toDateString()],
            'period_minutes' => $periodMinutes,
            'occupied_minutes' => $totalOccupied,
            'occupancy_rate' => $capacity > 0 ? round($totalOccupied / $capacity, 4) : 0,
            'sessions_count' => $sessions->count(),
            'stations' => $stations->values(),
        ]);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable} [start inclusive, end exclusive]
     */
    private function period(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $from = CarbonImmutable::parse($validated['from'])->startOfDay();
        $to = CarbonImmutable::parse($validated['to'])->addDay()->startOfDay();

        if ($from->diffInDays($to) > 366) {
            throw ValidationException::withMessages(['to' => 'O período máximo é de um ano.']);
        }

        return [$from, $to];
    }

    private function overlapSeconds(StationSession $session, CarbonImmutable $from, mixed $windowEnd): int
    {
        $start = $session->started_at->greaterThan($from) ? $session->started_at : $from;
        $end = $session->closed_at ?? $windowEnd;
        $end = $end->lessThan($windowEnd) ? $end : $windowEnd;

        return max(0, (int) $start->diffInSeconds($end, false));
    }

    /**
     * @param  iterable<Payment>  $payments
     */
    private function sumInCents(iterable $payments): int
    {
        $total = 0;

        foreach ($payments as $payment) {
            $total += (int) round(((float) $payment->amount) * 100);
        }

        return $total;
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
