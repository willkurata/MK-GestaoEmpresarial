<?php

namespace App\Http\Controllers\Api\Gym;

use App\Actions\Gym\CalculateOccupancy;
use App\Actions\Gym\RecordInvoicePayment;
use App\Http\Controllers\Controller;
use App\Models\Gym\GymAccessEvent;
use App\Models\Gym\GymClass;
use App\Models\Gym\GymClassAttendance;
use App\Models\Gym\GymInvoice;
use App\Models\Gym\GymPayment;
use App\Models\Gym\GymStudent;
use App\Models\Gym\GymUser;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Management dashboard and reports. Every figure is computed from stored records only (no estimates).
 */
class ReportController extends Controller
{
    public function dashboard(Request $request, CalculateOccupancy $calculateOccupancy): JsonResponse
    {
        /** @var GymUser $user */
        $user = $request->user();
        $students = $user->visibleStudents()->whereNull('anonymized_at');
        $overdue = $this->invoices($user)->overdue();
        $monthStart = today()->startOfMonth();
        $units = $user->gym->units()
            ->where('status', 'ativa')
            ->when($user->restrictedUnitId(), fn ($query, int $unitId) => $query->whereKey($unitId))
            ->get();
        $occupancy = $calculateOccupancy->handle($units);

        return response()->json(['data' => [
            'students' => [
                'active' => (clone $students)->where('status', 'ativo')->count(),
                'inactive' => (clone $students)->where('status', 'inativo')->count(),
            ],
            'present_now' => array_sum(array_column($occupancy, 'present')),
            'occupancy' => $occupancy,
            'delinquency' => [
                'overdue_invoices' => (clone $overdue)->count(),
                'overdue_amount' => self::money((clone $overdue)->sum('amount')),
                'students_overdue' => (clone $overdue)->distinct()->count('gym_student_id'),
            ],
            'month' => [
                'received' => self::money($this->payments($user)->where('paid_at', '>=', $monthStart)->sum('amount')),
                'refunded' => self::money($this->payments($user)->where('status', GymPayment::STATUS_REFUNDED)->where('refunded_at', '>=', $monthStart)->sum('amount')),
                'pending_due' => self::money($this->invoices($user)
                    ->where('status', GymInvoice::STATUS_PENDING)
                    ->whereBetween('due_on', [$monthStart->toDateString(), today()->endOfMonth()->toDateString()])
                    ->sum('amount')),
            ],
        ]]);
    }

    /**
     * Turnstile flow: allowed entrances and exits and denied attempts per day, entrances per hour (peak hours)
     * and the reasons for denials (e.g. overdue invoices).
     */
    public function occupancy(Request $request): JsonResponse
    {
        /** @var GymUser $user */
        $user = $request->user();
        [$from, $to] = $this->period($request);
        $unitId = $request->integer('unit_id') ?: null;

        $events = GymAccessEvent::query()
            ->where('gym_id', $user->gym_id)
            ->whereBetween('occurred_at', [$from, $to->endOfDay()])
            ->when($user->restrictedUnitId(), fn (Builder $query, int $restricted) => $query->where('gym_unit_id', $restricted))
            ->when($unitId, fn (Builder $query, int $unitId) => $query->where('gym_unit_id', $unitId))
            ->get(['gym_student_id', 'direction', 'decision', 'reason', 'occurred_at']);
        $allowed = $events->where('decision', GymAccessEvent::ALLOWED);
        $entries = $allowed->where('direction', 'entrada');
        $denied = $events->where('decision', GymAccessEvent::DENIED);

        $byDay = $events->groupBy(fn (GymAccessEvent $event) => $event->occurred_at->toDateString())
            ->map(function (Collection $dayEvents, string $day): array {
                $dayEntries = $dayEvents->where('decision', GymAccessEvent::ALLOWED)->where('direction', 'entrada');

                return [
                    'date' => $day,
                    'entries' => $dayEntries->count(),
                    'exits' => $dayEvents->where('decision', GymAccessEvent::ALLOWED)->where('direction', 'saida')->count(),
                    'denied' => $dayEvents->where('decision', GymAccessEvent::DENIED)->count(),
                    'unique_students' => $dayEntries->pluck('gym_student_id')->filter()->unique()->count(),
                ];
            })->sortKeys()->values();
        $byHour = collect(range(0, 23))->map(fn (int $hour) => [
            'hour' => $hour,
            'entries' => $entries->filter(fn (GymAccessEvent $event) => $event->occurred_at->hour === $hour)->count(),
        ]);

        return response()->json(['data' => [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'total_entries' => $entries->count(),
            'total_exits' => $allowed->where('direction', 'saida')->count(),
            'total_denied' => $denied->count(),
            'denied_by_reason' => $denied->countBy(fn (GymAccessEvent $event) => $event->reason ?? 'sem_motivo')->sortKeys(),
            'by_day' => $byDay,
            'by_hour' => $byHour,
        ]]);
    }

    /**
     * Frequency per student (days with an allowed entrance plus class roll calls) and per class (sessions and attendance rate).
     */
    public function attendance(Request $request): JsonResponse
    {
        /** @var GymUser $user */
        $user = $request->user();
        [$from, $to] = $this->period($request);
        $validated = $request->validate([
            'class_id' => ['nullable', 'integer'],
            'student_id' => ['nullable', 'integer'],
        ]);
        $classId = $validated['class_id'] ?? null;
        $studentId = $validated['student_id'] ?? null;
        $visibleStudentIds = $user->visibleStudents()->select('id');

        $classes = GymClass::query()
            ->where('gym_id', $user->gym_id)
            ->when($user->restrictedUnitId(), fn (Builder $query, int $unitId) => $query->where('gym_unit_id', $unitId))
            ->when($classId, fn (Builder $query, int $classId) => $query->whereKey($classId))
            ->withCount('currentStudents')
            ->orderBy('name')
            ->get();

        $rollCalls = GymClassAttendance::query()
            ->whereIn('gym_class_id', $classes->modelKeys())
            ->whereIn('gym_student_id', $visibleStudentIds)
            ->whereDate('attended_on', '>=', $from->toDateString())
            ->whereDate('attended_on', '<=', $to->toDateString())
            ->when($studentId, fn (Builder $query, int $studentId) => $query->where('gym_student_id', $studentId))
            ->get(['gym_class_id', 'gym_student_id', 'attended_on', 'present']);

        $visits = GymAccessEvent::query()
            ->where('gym_id', $user->gym_id)
            ->whereIn('gym_student_id', $visibleStudentIds)
            ->where('direction', 'entrada')
            ->where('decision', GymAccessEvent::ALLOWED)
            ->whereBetween('occurred_at', [$from, $to->endOfDay()])
            ->when($studentId, fn (Builder $query, int $studentId) => $query->where('gym_student_id', $studentId))
            ->when($classId, fn (Builder $query) => $query->whereIn('gym_student_id', $rollCalls->pluck('gym_student_id')->unique()->values()))
            ->get(['gym_student_id', 'occurred_at']);

        $studentIds = $visits->pluck('gym_student_id')->merge($rollCalls->pluck('gym_student_id'))->filter()->unique();
        $students = $user->visibleStudents()->whereIn('id', $studentIds)->orderBy('name')->get(['id', 'name', 'status']);

        $byStudent = $students->map(function (GymStudent $student) use ($visits, $rollCalls): array {
            $studentVisits = $visits->where('gym_student_id', $student->id);
            $studentRollCalls = $rollCalls->where('gym_student_id', $student->id);

            return [
                'student_id' => $student->id,
                'name' => $student->name,
                'status' => $student->status,
                'visit_days' => $studentVisits->map(fn (GymAccessEvent $event) => $event->occurred_at->toDateString())->unique()->count(),
                'last_visit_at' => $studentVisits->max('occurred_at')?->toIso8601String(),
                'class_presences' => $studentRollCalls->where('present', true)->count(),
                'class_absences' => $studentRollCalls->where('present', false)->count(),
                'class_attendance_rate' => self::rate($studentRollCalls->where('present', true)->count(), $studentRollCalls->count()),
            ];
        })->values();

        $byClass = $classes->map(function (GymClass $class) use ($rollCalls): array {
            $classRollCalls = $rollCalls->where('gym_class_id', $class->id);
            $presences = $classRollCalls->where('present', true)->count();

            return [
                'class_id' => $class->id,
                'name' => $class->name,
                'unit_id' => $class->gym_unit_id,
                'enrolled_now' => $class->current_students_count,
                'sessions' => $classRollCalls->map(fn (GymClassAttendance $row) => $row->attended_on->toDateString())->unique()->count(),
                'presences' => $presences,
                'absences' => $classRollCalls->count() - $presences,
                'attendance_rate' => self::rate($presences, $classRollCalls->count()),
            ];
        })->values();

        return response()->json(['data' => [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'by_student' => $byStudent,
            'by_class' => $byClass,
        ]]);
    }

    public function delinquency(Request $request): JsonResponse
    {
        /** @var GymUser $user */
        $user = $request->user();
        $overdue = $this->invoices($user)->overdue()->with('student')->orderBy('due_on')->get();
        $activeStudents = $user->visibleStudents()->whereNull('anonymized_at')->where('status', 'ativo')->count();
        $studentsOverdue = $overdue->pluck('gym_student_id')->unique()->count();

        return response()->json(['data' => [
            'overdue_invoices' => $overdue->count(),
            'overdue_amount' => self::money($overdue->sum(fn (GymInvoice $invoice) => (float) $invoice->amount)),
            'students_overdue' => $studentsOverdue,
            'delinquency_rate' => $activeStudents > 0 ? round($studentsOverdue / $activeStudents * 100, 1) : null,
            'invoices' => $overdue->map(fn (GymInvoice $invoice) => [
                'id' => $invoice->id,
                'student_id' => $invoice->gym_student_id,
                'student_name' => $invoice->student->name,
                'amount' => $invoice->amount,
                'due_on' => $invoice->due_on->toDateString(),
                'days_late' => (int) $invoice->due_on->diffInDays(today()),
                'payment_method' => $invoice->payment_method,
            ])->values(),
        ]]);
    }

    /**
     * Money in (payments by method), money out (refunds) and net, per day.
     */
    public function cashFlow(Request $request): JsonResponse
    {
        /** @var GymUser $user */
        $user = $request->user();
        [$from, $to] = $this->period($request);
        $end = $to->endOfDay();

        $received = $this->payments($user)->whereBetween('paid_at', [$from, $end])->get(['method', 'amount', 'paid_at']);
        $refunded = $this->payments($user)
            ->where('status', GymPayment::STATUS_REFUNDED)
            ->whereBetween('refunded_at', [$from, $end])
            ->get(['method', 'amount', 'refunded_at']);

        $days = collect();
        for ($day = $from; $day->lte($to); $day = $day->addDay()) {
            $date = $day->toDateString();
            $in = RecordInvoicePayment::toCents((string) $received->filter(fn (GymPayment $payment) => $payment->paid_at->toDateString() === $date)->sum(fn (GymPayment $payment) => (float) $payment->amount));
            $out = RecordInvoicePayment::toCents((string) $refunded->filter(fn (GymPayment $payment) => $payment->refunded_at?->toDateString() === $date)->sum(fn (GymPayment $payment) => (float) $payment->amount));
            $days->push(['date' => $date, 'received' => self::cents($in), 'refunded' => self::cents($out), 'net' => self::cents($in - $out)]);
        }

        $receivedCents = RecordInvoicePayment::toCents((string) $received->sum(fn (GymPayment $payment) => (float) $payment->amount));
        $refundedCents = RecordInvoicePayment::toCents((string) $refunded->sum(fn (GymPayment $payment) => (float) $payment->amount));

        return response()->json(['data' => [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'received' => self::cents($receivedCents),
            'refunded' => self::cents($refundedCents),
            'net' => self::cents($receivedCents - $refundedCents),
            'received_by_method' => $received->groupBy('method')->map(fn (Collection $payments) => self::money($payments->sum(fn (GymPayment $payment) => (float) $payment->amount))),
            'expected_in_period' => self::money($this->invoices($user)
                ->where('status', GymInvoice::STATUS_PENDING)
                ->whereBetween('due_on', [$from->toDateString(), $to->toDateString()])
                ->sum('amount')),
            'by_day' => $days,
        ]]);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function period(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'unit_id' => ['nullable', 'integer'],
        ]);
        $from = CarbonImmutable::parse($validated['from'] ?? today()->startOfMonth())->startOfDay();
        $to = CarbonImmutable::parse($validated['to'] ?? today())->startOfDay();

        if ($from->diffInDays($to) > 366) {
            abort(422, 'O período máximo é de 1 ano.');
        }

        return [$from, $to];
    }

    /**
     * @return Builder<GymInvoice>
     */
    private function invoices(GymUser $user): Builder
    {
        return GymInvoice::query()
            ->where('gym_id', $user->gym_id)
            ->when($user->restrictedUnitId() !== null, fn (Builder $query) => $query->whereIn('gym_student_id', $user->visibleStudents()->select('id')));
    }

    /**
     * @return Builder<GymPayment>
     */
    private function payments(GymUser $user): Builder
    {
        return GymPayment::query()
            ->where('gym_id', $user->gym_id)
            ->when($user->restrictedUnitId() !== null, fn (Builder $query) => $query->whereIn(
                'gym_invoice_id',
                $this->invoices($user)->select('id'),
            ));
    }

    private static function money(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    private static function rate(int $part, int $total): ?float
    {
        return $total > 0 ? round($part / $total * 100, 1) : null;
    }

    private static function cents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
