<?php

namespace App\Http\Controllers\CyberCoffe;

use App\Models\CyberCoffe\Attendant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AttendantController extends CyberCoffeController
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->currentStore($request)->attendants()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $store = $this->currentStore($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'alpha_dash', 'max:60', Rule::unique('cc_attendants')->where('store_id', $store->id)],
            'password' => ['required', 'string', Password::defaults()],
            'role' => ['required', Rule::in(Attendant::ROLES)],
        ]);

        $attendant = $store->attendants()->create([...$validated, 'name' => trim($validated['name'])]);

        return response()->json(['data' => $attendant], 201);
    }

    public function update(Request $request, int $attendant): JsonResponse
    {
        $attendant = $this->currentStore($request)->attendants()->findOrFail($attendant);
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'password' => ['sometimes', 'string', Password::defaults()],
            'role' => ['sometimes', Rule::in(Attendant::ROLES)],
            'status' => ['sometimes', Rule::in(['ativo', 'inativo'])],
        ]);

        if ($attendant->is($this->currentAttendant($request)) && (($validated['status'] ?? 'ativo') !== 'ativo' || ($validated['role'] ?? 'gerente') !== 'gerente')) {
            throw ValidationException::withMessages(['status' => 'Você não pode inativar ou rebaixar a própria conta.']);
        }

        $attendant->update($validated);

        if (($validated['status'] ?? null) === 'inativo' || isset($validated['password'])) {
            $attendant->apiTokens()->delete();
        }

        return response()->json(['data' => $attendant->fresh()]);
    }
}
