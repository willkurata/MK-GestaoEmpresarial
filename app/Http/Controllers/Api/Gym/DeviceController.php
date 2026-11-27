<?php

namespace App\Http\Controllers\Api\Gym;

use App\Actions\Gym\DecideStudentAccess;
use App\Http\Controllers\Controller;
use App\Models\Gym\GymAccessDevice;
use App\Models\Gym\GymAccessEvent;
use App\Models\Gym\GymStudent;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Endpoints called by the turnstile / local edge agent, authenticated with the device token.
 * The terminal identifies the student (face, QR or card) and sends the student's access code.
 * When the face or QR code cannot be read, the keypad fallback sends the matrícula and the student's PIN instead.
 */
class DeviceController extends Controller
{
    /**
     * Online mode: the server decides whether the turnstile opens, and the attempt is logged.
     */
    public function accessCheck(Request $request, DecideStudentAccess $decideStudentAccess): JsonResponse
    {
        $device = $this->device($request);
        $validated = $request->validate([
            'method' => ['required', Rule::in(GymAccessEvent::DEVICE_METHODS)],
            'direction' => ['required', Rule::in(GymAccessEvent::DIRECTIONS)],
            'access_code' => ['exclude_if:method,codigo', 'required', 'string', 'max:64'],
            'registration_number' => ['exclude_unless:method,codigo', 'required', 'string', 'max:12'],
            'pin' => ['exclude_unless:method,codigo', 'required', 'string', 'regex:/^\d{4,6}$/'],
        ]);

        if ($validated['method'] === 'codigo') {
            $student = GymStudent::query()
                ->where('gym_id', $device->gym_id)
                ->where('registration_number', $validated['registration_number'])
                ->first();
            $decision = $this->checkKeypadPin($student, $validated['pin'])
                ?? $decideStudentAccess->handle($student, $validated['direction'], $validated['method']);
        } else {
            $student = GymStudent::query()
                ->where('gym_id', $device->gym_id)
                ->where('access_code', $validated['access_code'])
                ->first();
            $decision = $decideStudentAccess->handle($student, $validated['direction'], $validated['method']);
        }

        GymAccessEvent::create([
            'gym_id' => $device->gym_id,
            'gym_unit_id' => $device->gym_unit_id,
            'gym_access_device_id' => $device->id,
            'gym_student_id' => $student?->id,
            'direction' => $validated['direction'],
            'method' => $validated['method'],
            'decision' => $decision['allowed'] ? GymAccessEvent::ALLOWED : GymAccessEvent::DENIED,
            'reason' => $decision['reason'],
            'occurred_at' => now(),
        ]);

        return response()->json([
            'allowed' => $decision['allowed'],
            'reason' => $decision['reason'],
            'display_name' => $student && ! in_array($decision['reason'], ['senha_invalida', 'senha_bloqueada'], true) ? strtok($student->name, ' ') : null,
        ]);
    }

    /**
     * Keypad fallback: a wrong or locked PIN denies before any other rule, so the reply never reveals the student's status.
     *
     * @return array{allowed: false, reason: string}|null
     */
    private function checkKeypadPin(?GymStudent $student, string $pin): ?array
    {
        if ($student === null || $student->anonymized_at !== null) {
            return null;
        }

        return match ($student->attemptAccessPin($pin)) {
            'valida' => null,
            'bloqueada' => ['allowed' => false, 'reason' => 'senha_bloqueada'],
            'invalida' => ['allowed' => false, 'reason' => 'senha_invalida'],
        };
    }

    /**
     * Offline mode: the edge agent uploads passages it decided locally while the internet was down.
     */
    public function storeEvents(Request $request): JsonResponse
    {
        $device = $this->device($request);
        $validated = $request->validate([
            'events' => ['required', 'array', 'max:500'],
            'events.*.access_code' => ['required', 'string', 'max:64'],
            'events.*.direction' => ['required', Rule::in(GymAccessEvent::DIRECTIONS)],
            'events.*.method' => ['required', Rule::in(GymAccessEvent::DEVICE_METHODS)],
            'events.*.decision' => ['required', Rule::in([GymAccessEvent::ALLOWED, GymAccessEvent::DENIED])],
            'events.*.reason' => ['nullable', 'string', 'max:100'],
            'events.*.occurred_at' => ['required', 'date', 'before_or_equal:now'],
        ]);

        $studentIds = GymStudent::query()
            ->where('gym_id', $device->gym_id)
            ->whereIn('access_code', array_column($validated['events'], 'access_code'))
            ->pluck('id', 'access_code');

        foreach ($validated['events'] as $event) {
            GymAccessEvent::create([
                'gym_id' => $device->gym_id,
                'gym_unit_id' => $device->gym_unit_id,
                'gym_access_device_id' => $device->id,
                'gym_student_id' => $studentIds->get($event['access_code']),
                'direction' => $event['direction'],
                'method' => $event['method'],
                'decision' => $event['decision'],
                'reason' => $event['reason'] ?? null,
                'occurred_at' => CarbonImmutable::parse($event['occurred_at']),
            ]);
        }

        return response()->json(['stored' => count($validated['events'])], 201);
    }

    /**
     * Allow-list cached by the edge agent so the turnstile keeps working offline.
     * Students not listed must be denied; a student whose face is not listed must have the template removed.
     */
    public function sync(Request $request, DecideStudentAccess $decideStudentAccess): JsonResponse
    {
        $device = $this->device($request);
        $students = GymStudent::query()
            ->where('gym_id', $device->gym_id)
            ->where('status', 'ativo')
            ->whereNull('anonymized_at')
            ->with('activeBiometricConsent')
            ->get();

        $allowed = $students
            ->filter(fn (GymStudent $student) => $decideStudentAccess->handle($student, 'entrada', 'qrcode')['allowed'])
            ->map(fn (GymStudent $student) => [
                'access_code' => $student->access_code,
                'display_name' => strtok($student->name, ' '),
                'face_authorized' => $student->face_status === GymStudent::FACE_ENROLLED && $student->activeBiometricConsent !== null,
            ])
            ->values();

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'unit_id' => $device->gym_unit_id,
            'students' => $allowed,
        ]);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $device = $this->device($request);

        return response()->json(['device_id' => $device->id, 'server_time' => now()->toIso8601String()]);
    }

    private function device(Request $request): GymAccessDevice
    {
        /** @var GymAccessDevice $device */
        $device = $request->attributes->get('gym_device');

        return $device;
    }
}
