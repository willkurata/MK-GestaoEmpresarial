<?php

namespace App\Http\Controllers\Api\Gym;

use App\Http\Controllers\Controller;
use App\Models\Gym\GymUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;

class UnitController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var GymUser $user */
        $user = $request->user();

        return JsonResource::collection($user->gym->units()->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        /** @var GymUser $user */
        $user = $request->user();
        $validated = $request->validate($this->rules($user->gym_id));

        return JsonResource::make($user->gym->units()->create($validated))->response()->setStatusCode(201);
    }

    public function update(Request $request, int $unit): JsonResource
    {
        /** @var GymUser $user */
        $user = $request->user();
        $unit = $user->gym->units()->findOrFail($unit);
        $unit->update($request->validate($this->rules($user->gym_id, $unit->id)));

        return JsonResource::make($unit);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(int $gymId, ?int $ignoreUnitId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('gym_units', 'name')->where('gym_id', $gymId)->ignore($ignoreUnitId)],
            'address' => ['nullable', 'string', 'max:255'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'status' => ['sometimes', Rule::in(['ativa', 'inativa'])],
        ];
    }
}
