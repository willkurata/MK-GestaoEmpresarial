<?php

namespace App\Http\Controllers\Api\Gym;

use App\Actions\Gym\CalculateOccupancy;
use App\Http\Controllers\Controller;
use App\Http\Resources\Gym\GymAccessEventResource;
use App\Models\Gym\GymAccessEvent;
use App\Models\Gym\GymAuditLog;
use App\Models\Gym\GymUser;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Front-desk view of the turnstiles: history, live occupancy and audited manual release.
 */
class AccessController extends Controller
{
    public function events(Request $request): AnonymousResourceCollection
    {
        /** @var GymUser $user */
        $user = $request->user();
        $validated = $request->validate([
            'unit_id' => ['nullable', 'integer'],
            'student_id' => ['nullable', 'integer'],
            'decision' => ['nullable', Rule::in([GymAccessEvent::ALLOWED, GymAccessEvent::DENIED])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $events = GymAccessEvent::query()
            ->where('gym_id', $user->gym_id)
            ->with('student')
            ->when($user->restrictedUnitId(), fn (Builder $query, int $unitId) => $query->where('gym_unit_id', $unitId))
            ->when($validated['unit_id'] ?? null, fn (Builder $query, int $unitId) => $query->where('gym_unit_id', $unitId))
            ->when($validated['student_id'] ?? null, fn (Builder $query, int $studentId) => $query->where('gym_student_id', $studentId))
            ->when($validated['decision'] ?? null, fn (Builder $query, string $decision) => $query->where('decision', $decision))
            ->when($validated['from'] ?? null, fn (Builder $query, string $from) => $query->where('occurred_at', '>=', $from))
            ->when($validated['to'] ?? null, fn (Builder $query, string $to) => $query->where('occurred_at', '<', CarbonImmutable::parse($to)->addDay()))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->cursorPaginate(100);

        return GymAccessEventResource::collection($events);
    }

    public function occupancy(Request $request, CalculateOccupancy $calculateOccupancy): JsonResponse
    {
        /** @var GymUser $user */
        $user = $request->user();
        $units = $user->gym->units()
            ->where('status', 'ativa')
            ->when($user->restrictedUnitId(), fn ($query, int $unitId) => $query->whereKey($unitId))
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $calculateOccupancy->handle($units), 'calculated_at' => now()->toIso8601String()]);
    }

    /**
     * Releases the turnstile by hand (e.g. visitor, equipment failure). Always audited with a reason.
     */
    public function manualRelease(Request $request): JsonResponse
    {
        /** @var GymUser $user */
        $user = $request->user();
        $validated = $request->validate([
            'unit_id' => ['required', 'integer', Rule::exists('gym_units', 'id')->where('gym_id', $user->gym_id)],
            'student_id' => ['nullable', 'integer'],
            'direction' => ['required', Rule::in(GymAccessEvent::DIRECTIONS)],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        if (! $user->canAccessUnit($validated['unit_id'])) {
            throw ValidationException::withMessages(['unit_id' => 'Você não gerencia esta unidade.']);
        }

        $student = isset($validated['student_id'])
            ? $user->gym->students()->findOrFail((int) $validated['student_id'])
            : null;

        $event = GymAccessEvent::create([
            'gym_id' => $user->gym_id,
            'gym_unit_id' => $validated['unit_id'],
            'gym_student_id' => $student?->id,
            'direction' => $validated['direction'],
            'method' => 'manual',
            'decision' => GymAccessEvent::ALLOWED,
            'reason' => $validated['reason'],
            'recorded_by_user_id' => $user->id,
            'occurred_at' => now(),
        ]);
        GymAuditLog::record($request, $user, 'catraca.liberacao_manual', $event, ['reason' => $validated['reason']]);

        return GymAccessEventResource::make($event)->response()->setStatusCode(201);
    }
}
