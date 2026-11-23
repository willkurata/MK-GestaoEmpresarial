<?php

namespace App\Http\Controllers\CyberCoffe;

use App\Http\Resources\CyberCoffe\CustomerResource;
use App\Models\CyberCoffe\ApiToken;
use App\Models\CyberCoffe\Customer;
use App\Models\CyberCoffe\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends CyberCoffeController
{
    private const int ATTENDANT_TOKEN_HOURS = 12;

    private const int CUSTOMER_TOKEN_DAYS = 30;

    public function attendantLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_code' => ['required', 'string', 'max:40'],
            'username' => ['required', 'string', 'max:60'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $store = $this->activeStore($validated['store_code']);
        $attendant = $store?->attendants()->where('username', $validated['username'])->first();

        if ($attendant === null || ! $attendant->isActive() || ! Hash::check($validated['password'], $attendant->password)) {
            throw ValidationException::withMessages(['username' => 'Credenciais inválidas.']);
        }

        $token = ApiToken::issue($attendant, $validated['device_name'] ?? 'painel', now()->addHours(self::ATTENDANT_TOKEN_HOURS));

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'attendant' => $attendant->only(['id', 'name', 'username', 'role']),
            'store' => $store->only(['id', 'code', 'name']),
        ]);
    }

    public function customerLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_code' => ['required', 'string', 'max:40'],
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string'],
        ]);

        $store = $this->activeStore($validated['store_code']);
        $customer = $store?->customers()->where('phone', $this->normalizePhone($validated['phone']))->first();

        if ($customer === null || $customer->password === null || ! $customer->isActive() || ! Hash::check($validated['password'], $customer->password)) {
            throw ValidationException::withMessages(['phone' => 'Credenciais inválidas.']);
        }

        return $this->customerTokenResponse($customer, $store);
    }

    public function customerRegister(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_code' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        $store = $this->activeStore($validated['store_code']);

        if ($store === null) {
            throw ValidationException::withMessages(['store_code' => 'Loja não encontrada.']);
        }

        $phone = $this->normalizePhone($validated['phone']);
        validator(['phone' => $phone], [
            'phone' => ['digits_between:10,13', Rule::unique('cc_customers', 'phone')->where('store_id', $store->id)],
        ])->validate();

        $customer = $store->customers()->create([
            'name' => trim($validated['name']),
            'phone' => $phone,
            'password' => $validated['password'],
        ]);

        return $this->customerTokenResponse($customer, $store, 201);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->attributes->get('cybercoffe.token')->delete();

        return response()->json(['message' => 'Sessão encerrada.']);
    }

    public function me(Request $request): JsonResponse
    {
        $attendant = $this->currentAttendant($request);

        return response()->json([
            'attendant' => $attendant->only(['id', 'name', 'username', 'role']),
            'store' => $this->currentStore($request)->only(['id', 'code', 'name']),
        ]);
    }

    private function activeStore(string $code): ?Store
    {
        return Store::query()->where('code', mb_strtolower(trim($code)))->where('status', 'ativa')->first();
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D/', '', $phone);
    }

    private function customerTokenResponse(Customer $customer, Store $store, int $status = 200): JsonResponse
    {
        $token = ApiToken::issue($customer, 'cliente', now()->addDays(self::CUSTOMER_TOKEN_DAYS));

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'customer' => new CustomerResource($customer),
            'store' => $store->only(['id', 'code', 'name']),
        ], $status);
    }
}
