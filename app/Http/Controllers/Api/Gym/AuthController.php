<?php

namespace App\Http\Controllers\Api\Gym;

use App\Http\Controllers\Controller;
use App\Models\Gym\Gym;
use App\Models\Gym\GymApiToken;
use App\Models\Gym\GymUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Exchanges gym + e-mail + password for a bearer token (students, managers and administrators).
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'gym' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $gym = Gym::query()->where('slug', mb_strtolower($validated['gym']))->where('status', 'ativa')->first();
        $user = $gym?->users()->where('email', mb_strtolower($validated['email']))->first();

        if ($user === null || ! $user->isActive() || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Academia, e-mail ou senha inválidos.']);
        }

        $plainToken = $user->issueApiToken($validated['device_name'] ?? 'app');

        return response()->json([
            'token' => $plainToken,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var GymUser $user */
        $user = $request->user();

        return response()->json(['data' => $this->userPayload($user)]);
    }

    public function logout(Request $request): Response
    {
        /** @var GymApiToken|null $token */
        $token = $request->attributes->get('gym_api_token');
        $token?->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(GymUser $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'unit_id' => $user->gym_unit_id,
            'student_id' => $user->student?->id,
            'gym' => ['id' => $user->gym_id, 'name' => $user->gym->name, 'slug' => $user->gym->slug],
        ];
    }
}
