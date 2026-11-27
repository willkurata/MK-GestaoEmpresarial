<?php

namespace App\Http\Controllers\Api\Gym;

use App\Http\Controllers\Controller;
use App\Http\Resources\Gym\GymStudentResource;
use App\Models\Gym\GymAuditLog;
use App\Models\Gym\GymStudent;
use App\Models\Gym\GymUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * PIN used with the matrícula on the turnstile keypad when facial recognition or the QR code is unavailable.
 * Only the hash is stored; setting a new PIN also unlocks a fallback blocked by wrong attempts.
 */
class AccessPinController extends Controller
{
    public function updateForStudent(Request $request, int $student): GymStudentResource
    {
        /** @var GymUser $user */
        $user = $request->user();
        $student = $user->visibleStudents()->whereNull('anonymized_at')->findOrFail($student);
        $validated = $request->validate(self::pinRules());

        $student->setAccessPin($validated['pin']);
        GymAuditLog::record($request, $user, 'catraca.senha_definida', $student);

        return GymStudentResource::make($student);
    }

    /**
     * The student changes their own PIN in the app, confirming the account password.
     */
    public function updateOwn(Request $request): GymStudentResource
    {
        /** @var GymUser $user */
        $user = $request->user();
        /** @var GymStudent $student */
        $student = $user->student()->whereNull('anonymized_at')->firstOrFail();
        $validated = $request->validate([
            'password' => ['required', 'string'],
            ...self::pinRules(),
        ]);

        if (! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => 'Senha da conta incorreta.']);
        }

        $student->setAccessPin($validated['pin']);
        GymAuditLog::record($request, $user, 'catraca.senha_definida_pelo_titular', $student);

        return GymStudentResource::make($student);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private static function pinRules(): array
    {
        return [
            'pin' => ['required', 'string', 'regex:/^\d{4,6}$/', 'confirmed'],
        ];
    }
}
