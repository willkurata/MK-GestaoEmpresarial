<?php

namespace App\Http\Controllers\Api\Gym;

use App\Http\Controllers\Controller;
use App\Models\Gym\GymUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Administrators manage the gym's staff accounts (gestores and administradores).
 */
class StaffUserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var GymUser $user */
        $user = $request->user();

        return JsonResource::collection($user->gym->users()->whereIn('role', GymUser::STAFF_ROLES)->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        /** @var GymUser $user */
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('gym_users', 'email')->where('gym_id', $user->gym_id)],
            'password' => ['required', 'string', Password::defaults(), 'min:10'],
            'role' => ['required', Rule::in(GymUser::STAFF_ROLES)],
            'unit_id' => ['nullable', 'integer', Rule::exists('gym_units', 'id')->where('gym_id', $user->gym_id)],
        ]);

        $staff = $user->gym->users()->create([
            'name' => $validated['name'],
            'email' => mb_strtolower($validated['email']),
            'password' => $validated['password'],
            'role' => $validated['role'],
            'gym_unit_id' => $validated['unit_id'] ?? null,
        ]);

        return JsonResource::make($staff)->response()->setStatusCode(201);
    }

    public function update(Request $request, int $staffUser): JsonResource
    {
        /** @var GymUser $user */
        $user = $request->user();
        $staff = $user->gym->users()->whereIn('role', GymUser::STAFF_ROLES)->findOrFail($staffUser);
        $validated = $request->validate([
            'role' => ['sometimes', Rule::in(GymUser::STAFF_ROLES)],
            'status' => ['sometimes', Rule::in(['ativo', 'inativo'])],
            'unit_id' => ['sometimes', 'nullable', 'integer', Rule::exists('gym_units', 'id')->where('gym_id', $user->gym_id)],
        ]);

        if ($staff->is($user) && (($validated['status'] ?? 'ativo') !== 'ativo' || ($validated['role'] ?? $staff->role) !== GymUser::ROLE_ADMINISTRATOR)) {
            throw ValidationException::withMessages(['role' => 'Você não pode remover o próprio acesso de administrador.']);
        }

        $staff->fill(array_filter([
            'role' => $validated['role'] ?? null,
            'status' => $validated['status'] ?? null,
        ]));

        if (array_key_exists('unit_id', $validated)) {
            $staff->gym_unit_id = $validated['unit_id'];
        }

        $staff->save();

        if ($staff->status !== 'ativo') {
            $staff->apiTokens()->delete();
        }

        return JsonResource::make($staff);
    }
}
