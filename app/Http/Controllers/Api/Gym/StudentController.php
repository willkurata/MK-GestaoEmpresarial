<?php

namespace App\Http\Controllers\Api\Gym;

use App\Http\Controllers\Controller;
use App\Http\Resources\Gym\GymGuardianResource;
use App\Http\Resources\Gym\GymStudentResource;
use App\Models\Gym\GymGuardian;
use App\Models\Gym\GymStudent;
use App\Models\Gym\GymUser;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class StudentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var GymUser $user */
        $user = $request->user();
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['ativo', 'inativo'])],
            'search' => ['nullable', 'string', 'max:100'],
            'unit_id' => ['nullable', 'integer'],
        ]);

        $students = $user->visibleStudents()
            ->with('activeBiometricConsent')
            ->whereNull('anonymized_at')
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($validated['unit_id'] ?? null, fn ($query, int $unitId) => $query->where('gym_unit_id', $unitId))
            ->when($validated['search'] ?? null, fn ($query, string $search) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%');

                if (strlen(GymStudent::normalizeCpf($search)) === 11) {
                    $query->orWhere('cpf_hash', GymStudent::hashCpf($search));
                }
            }))
            ->orderBy('name')
            ->paginate(50);

        return GymStudentResource::collection($students);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var GymUser $user */
        $user = $request->user();
        $validated = $request->validate([
            ...$this->studentRules($user),
            'guardian' => ['nullable', 'array'],
            ...self::guardianRules('guardian.'),
        ]);
        $cpf = GymStudent::normalizeCpf($validated['cpf']);
        $this->ensureCpfIsAvailable($user->gym_id, $cpf);

        if ($this->isMinor($validated['birth_date']) && empty($validated['guardian'])) {
            throw ValidationException::withMessages(['guardian' => 'Aluno menor de 18 anos exige um responsável legal.']);
        }

        $student = DB::transaction(function () use ($user, $validated, $cpf): GymStudent {
            $student = $user->gym->students()->create([
                'gym_unit_id' => $validated['unit_id'],
                'name' => $validated['name'],
                'cpf' => $cpf,
                'email' => isset($validated['email']) ? mb_strtolower($validated['email']) : null,
                'phone' => $validated['phone'] ?? null,
                'birth_date' => $validated['birth_date'],
                'status' => 'ativo',
            ]);

            if (! empty($validated['guardian'])) {
                $this->createGuardian($student, $validated['guardian']);
            }

            return $student;
        });

        return GymStudentResource::make($student->load('guardians', 'activeBiometricConsent'))->response()->setStatusCode(201);
    }

    public function show(Request $request, int $student): GymStudentResource
    {
        return GymStudentResource::make($this->findStudent($request, $student)->load('guardians', 'activeBiometricConsent'));
    }

    public function update(Request $request, int $student): GymStudentResource
    {
        /** @var GymUser $user */
        $user = $request->user();
        $student = $this->findStudent($request, $student);
        $validated = $request->validate([
            'unit_id' => ['sometimes', 'integer', Rule::exists('gym_units', 'id')->where('gym_id', $user->gym_id)],
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'status' => ['sometimes', Rule::in(['ativo', 'inativo'])],
        ]);

        if (isset($validated['unit_id']) && ! $user->canAccessUnit($validated['unit_id'])) {
            throw ValidationException::withMessages(['unit_id' => 'Você não gerencia esta unidade.']);
        }

        $student->fill(Arr::except($validated, 'unit_id'));

        if (isset($validated['unit_id'])) {
            $student->gym_unit_id = $validated['unit_id'];
        }

        $student->save();

        return GymStudentResource::make($student->load('guardians', 'activeBiometricConsent'));
    }

    public function storeGuardian(Request $request, int $student): JsonResponse
    {
        $student = $this->findStudent($request, $student);
        $guardian = $this->createGuardian($student, $request->validate(self::guardianRules('', required: true)));

        return GymGuardianResource::make($guardian)->response()->setStatusCode(201);
    }

    /**
     * Creates the student's own login (role aluno) so they can use the app.
     */
    public function grantPortalAccess(Request $request, int $student): JsonResponse
    {
        /** @var GymUser $user */
        $user = $request->user();
        $student = $this->findStudent($request, $student);

        if ($student->gym_user_id !== null) {
            throw ValidationException::withMessages(['student' => 'Este aluno já possui acesso ao aplicativo.']);
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('gym_users', 'email')->where('gym_id', $user->gym_id)],
            'password' => ['required', 'string', Password::defaults(), 'min:10'],
        ]);

        DB::transaction(function () use ($user, $student, $validated): void {
            $account = $user->gym->users()->create([
                'name' => $student->name,
                'email' => mb_strtolower($validated['email']),
                'password' => $validated['password'],
                'role' => GymUser::ROLE_STUDENT,
            ]);
            $student->forceFill(['gym_user_id' => $account->id])->save();
        });

        return GymStudentResource::make($student->refresh())->response()->setStatusCode(201);
    }

    /**
     * @return array<string, mixed>
     */
    private function studentRules(GymUser $user): array
    {
        return [
            'unit_id' => ['required', 'integer', Rule::exists('gym_units', 'id')->where('gym_id', $user->gym_id)->where('status', 'ativa'), function (string $attribute, mixed $value, Closure $fail) use ($user): void {
                if (! $user->canAccessUnit((int) $value)) {
                    $fail('Você não gerencia esta unidade.');
                }
            }],
            'name' => ['required', 'string', 'max:255'],
            'cpf' => ['required', 'string', 'max:14', function (string $attribute, mixed $value, Closure $fail): void {
                if (strlen(GymStudent::normalizeCpf((string) $value)) !== 11) {
                    $fail('Informe um CPF com 11 dígitos.');
                }
            }],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['required', 'date', 'before:today'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function guardianRules(string $prefix, bool $required = false): array
    {
        $presence = $required ? 'required' : 'required_with:guardian';

        return [
            $prefix.'name' => [$presence, 'string', 'max:255'],
            $prefix.'cpf' => [$presence, 'string', 'max:14'],
            $prefix.'relationship' => [$presence, 'string', 'max:50'],
            $prefix.'phone' => ['nullable', 'string', 'max:20'],
            $prefix.'email' => ['nullable', 'email', 'max:255'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createGuardian(GymStudent $student, array $data): GymGuardian
    {
        $cpf = GymStudent::normalizeCpf((string) $data['cpf']);

        if (strlen($cpf) !== 11) {
            throw ValidationException::withMessages(['guardian.cpf' => 'Informe um CPF com 11 dígitos para o responsável.']);
        }

        return $student->guardians()->create([
            'gym_id' => $student->gym_id,
            'name' => $data['name'],
            'cpf' => $cpf,
            'relationship' => $data['relationship'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
        ]);
    }

    private function ensureCpfIsAvailable(int $gymId, string $cpf): void
    {
        $exists = GymStudent::query()->where('gym_id', $gymId)->where('cpf_hash', GymStudent::hashCpf($cpf))->exists();

        if ($exists) {
            throw ValidationException::withMessages(['cpf' => 'Já existe um aluno com este CPF nesta academia.']);
        }
    }

    private function isMinor(string $birthDate): bool
    {
        return CarbonImmutable::parse($birthDate)->diffInYears(now()) < 18;
    }

    private function findStudent(Request $request, int $studentId): GymStudent
    {
        /** @var GymUser $user */
        $user = $request->user();

        return $user->visibleStudents()->whereNull('anonymized_at')->findOrFail($studentId);
    }
}
