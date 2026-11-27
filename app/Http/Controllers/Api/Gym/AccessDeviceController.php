<?php

namespace App\Http\Controllers\Api\Gym;

use App\Http\Controllers\Controller;
use App\Models\Gym\GymAccessDevice;
use App\Models\Gym\GymAuditLog;
use App\Models\Gym\GymUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;

/**
 * Administrators register turnstiles/edge agents. The device token is shown only once.
 */
class AccessDeviceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var GymUser $user */
        $user = $request->user();

        return JsonResource::collection($user->gym->accessDevices()->orderBy('gym_unit_id')->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        /** @var GymUser $user */
        $user = $request->user();
        $validated = $request->validate([
            'unit_id' => ['required', 'integer', Rule::exists('gym_units', 'id')->where('gym_id', $user->gym_id)],
            'name' => ['required', 'string', 'max:255'],
            'vendor' => ['required', Rule::in(GymAccessDevice::VENDORS)],
        ]);

        $device = $user->gym->accessDevices()->create([
            'gym_unit_id' => $validated['unit_id'],
            'name' => $validated['name'],
            'vendor' => $validated['vendor'],
            'token_hash' => hash('sha256', 'pending-'.uniqid('', true)),
        ]);
        $plainToken = $device->rotateToken();
        GymAuditLog::record($request, $user, 'catraca.dispositivo_cadastrado', $device);

        return response()->json(['data' => $device, 'device_token' => $plainToken], 201);
    }

    public function update(Request $request, int $device): JsonResource
    {
        /** @var GymUser $user */
        $user = $request->user();
        $device = $user->gym->accessDevices()->findOrFail($device);
        $device->update($request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['ativo', 'inativo'])],
        ]));

        return JsonResource::make($device);
    }

    public function rotateToken(Request $request, int $device): JsonResponse
    {
        /** @var GymUser $user */
        $user = $request->user();
        $device = $user->gym->accessDevices()->findOrFail($device);
        $plainToken = $device->rotateToken();
        GymAuditLog::record($request, $user, 'catraca.token_rotacionado', $device);

        return response()->json(['data' => $device, 'device_token' => $plainToken]);
    }
}
