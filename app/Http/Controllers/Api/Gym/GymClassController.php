<?php

namespace App\Http\Controllers\Api\Gym;

use App\Http\Controllers\Controller;
use App\Http\Resources\Gym\GymClassResource;
use App\Models\Gym\GymClass;
use App\Models\Gym\GymClassAttendance;
use App\Models\Gym\GymUser;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Turmas: schedule, enrolment history (joining and leaving) and roll call (chamada) used by the frequency report.
 */
class GymClassController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var GymUser $user */
        $user = $request->user();
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['ativa', 'inativa'])],
            'unit_id' => ['nullable', 'integer'],
        ]);

        $classes = $this->visibleClasses($user)
            ->withCount('currentStudents')
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($validated['unit_id'] ?? null, fn (Builder $query, int $unitId) => $query->where('gym_unit_id', $unitId))
            ->orderBy('name')
            ->paginate(50);

        return GymClassResource::collection($classes);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var GymUser $user */
        $user = $request->user();
        $validated = $request->validate($this->classRules($user, creating: true));

        $class = $user->gym->classes()->create([
            'gym_unit_id' => $validated['unit_id'],
            ...Arr::except($validated, 'unit_id'),
        ]);

        return GymClassResource::make($class->loadCount('currentStudents'))->response()->setStatusCode(201);
    }

    public function show(Request $request, int $class): GymClassResource
    {
        /** @var GymUser $user */
        $user = $request->user();

        return GymClassResource::make($this->visibleClasses($user)->with('students')->withCount('currentStudents')->findOrFail($class));
    }

    public function update(Request $request, int $class): GymClassResource
    {
        /** @var GymUser $user */
        $user = $request->user();
        $class = $this->visibleClasses($user)->findOrFail($class);
        $validated = $request->validate($this->classRules($user, creating: false));

        $class->update($validated);

        return GymClassResource::make($class->loadCount('currentStudents'));
    }

    public function enroll(Request $request, int $class): GymClassResource
    {
        /** @var GymUser $user */
        $user = $request->user();
        $class = $this->visibleClasses($user)->findOrFail($class);
        $validated = $request->validate(['student_id' => ['required', 'integer']]);
        $student = $user->visibleStudents()->whereNull('anonymized_at')->whereKey($validated['student_id'])->first();

        if ($student === null) {
            throw ValidationException::withMessages(['student_id' => 'Aluno não encontrado.']);
        }

        if ($student->status !== 'ativo' || $class->status !== 'ativa') {
            throw ValidationException::withMessages(['student_id' => 'Somente alunos ativos podem entrar em turmas ativas.']);
        }

        DB::transaction(function () use ($class, $student): void {
            /** @var GymClass $lockedClass */
            $lockedClass = GymClass::query()->lockForUpdate()->findOrFail($class->id);

            if ($lockedClass->currentStudents()->whereKey($student->id)->exists()) {
                throw ValidationException::withMessages(['student_id' => 'O aluno já está nesta turma.']);
            }

            if ($lockedClass->capacity !== null && $lockedClass->currentStudents()->count() >= $lockedClass->capacity) {
                throw ValidationException::withMessages(['student_id' => 'A turma está lotada.']);
            }

            $lockedClass->students()->attach($student->id, ['joined_on' => today()->toDateString()]);
        });

        return GymClassResource::make($class->load('students')->loadCount('currentStudents'));
    }

    /**
     * Leaving keeps the enrolment row (with `left_on`) so the frequency history stays intact.
     */
    public function unenroll(Request $request, int $class, int $student): GymClassResource
    {
        /** @var GymUser $user */
        $user = $request->user();
        $class = $this->visibleClasses($user)->findOrFail($class);
        $updated = $class->students()->wherePivotNull('left_on')->updateExistingPivot($student, ['left_on' => today()->toDateString()]);

        if ($updated === 0) {
            abort(404);
        }

        return GymClassResource::make($class->load('students')->loadCount('currentStudents'));
    }

    /**
     * Roll call for one date: students listed in `present_student_ids` are present, the other members on that date absent.
     * Sending the same date again corrects the previous roll call.
     */
    public function recordAttendance(Request $request, int $class): JsonResponse
    {
        /** @var GymUser $user */
        $user = $request->user();
        $class = $this->visibleClasses($user)->findOrFail($class);
        $validated = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'present_student_ids' => ['present', 'array'],
            'present_student_ids.*' => ['integer', 'distinct'],
        ]);
        $date = CarbonImmutable::parse($validated['date'])->toDateString();
        $memberIds = $class->students()
            ->wherePivot('joined_on', '<=', $date)
            ->where(fn (Builder $query) => $query->whereNull('gym_class_students.left_on')->orWhere('gym_class_students.left_on', '>', $date))
            ->pluck('gym_students.id');
        $presentIds = $request->collect('present_student_ids')->map(fn (mixed $id) => (int) $id);

        if ($memberIds->isEmpty()) {
            throw ValidationException::withMessages(['date' => 'A turma não tinha alunos nesta data.']);
        }

        if ($presentIds->diff($memberIds)->isNotEmpty()) {
            throw ValidationException::withMessages(['present_student_ids' => 'Há alunos que não pertenciam à turma nesta data.']);
        }

        DB::transaction(function () use ($class, $user, $date, $memberIds, $presentIds): void {
            foreach ($memberIds as $studentId) {
                $attendance = GymClassAttendance::query()
                    ->where('gym_class_id', $class->id)
                    ->where('gym_student_id', $studentId)
                    ->whereDate('attended_on', $date)
                    ->first() ?? new GymClassAttendance(['gym_class_id' => $class->id, 'gym_student_id' => $studentId, 'attended_on' => $date]);

                $attendance->fill([
                    'gym_id' => $class->gym_id,
                    'present' => $presentIds->contains($studentId),
                    'recorded_by_user_id' => $user->id,
                ])->save();
            }
        });

        return $this->attendanceResponse($class, $date)->setStatusCode(201);
    }

    public function attendance(Request $request, int $class): JsonResponse
    {
        /** @var GymUser $user */
        $user = $request->user();
        $class = $this->visibleClasses($user)->findOrFail($class);
        $validated = $request->validate(['date' => ['required', 'date']]);

        return $this->attendanceResponse($class, CarbonImmutable::parse($validated['date'])->toDateString());
    }

    private function attendanceResponse(GymClass $class, string $date): JsonResponse
    {
        $rows = $class->attendances()->with('student')->whereDate('attended_on', $date)->get();

        return response()->json(['data' => [
            'class_id' => $class->id,
            'date' => $date,
            'present' => $rows->where('present', true)->count(),
            'absent' => $rows->where('present', false)->count(),
            'students' => $rows->map(fn (GymClassAttendance $row) => [
                'student_id' => $row->gym_student_id,
                'name' => $row->student->name,
                'present' => $row->present,
            ])->sortBy('name')->values(),
        ]]);
    }

    /**
     * @return Builder<GymClass>
     */
    private function visibleClasses(GymUser $user): Builder
    {
        return GymClass::query()
            ->where('gym_id', $user->gym_id)
            ->when($user->restrictedUnitId(), fn (Builder $query, int $unitId) => $query->where('gym_unit_id', $unitId));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function classRules(GymUser $user, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return [
            'unit_id' => [$creating ? 'required' : 'prohibited', 'integer', Rule::exists('gym_units', 'id')->where('gym_id', $user->gym_id),
                function (string $attribute, mixed $value, Closure $fail) use ($user): void {
                    if (! $user->canAccessUnit((int) $value)) {
                        $fail('Você não gerencia esta unidade.');
                    }
                }],
            'name' => [$required, 'string', 'max:120'],
            'modality' => ['nullable', 'string', 'max:60'],
            'instructor_name' => ['nullable', 'string', 'max:120'],
            'schedule' => [$required, 'array', 'min:1', 'max:14'],
            'schedule.*.weekday' => ['required', 'integer', 'between:0,6'],
            'schedule.*.starts_at' => ['required', 'date_format:H:i'],
            'schedule.*.ends_at' => ['required', 'date_format:H:i', 'after:schedule.*.starts_at'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'status' => ['sometimes', Rule::in(['ativa', 'inativa'])],
        ];
    }
}
