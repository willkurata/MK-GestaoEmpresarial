<?php

namespace App\Http\Controllers\Api\Gym;

use App\Http\Controllers\Controller;
use App\Models\Gym\GymPlan;
use App\Models\Gym\GymUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    /**
     * Students only see active plans; staff see all of them.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var GymUser $user */
        $user = $request->user();

        return JsonResource::collection($user->gym->plans()
            ->when($user->role === GymUser::ROLE_STUDENT, fn ($query) => $query->where('status', 'ativo'))
            ->orderBy('price')
            ->get());
    }

    public function store(Request $request): JsonResponse
    {
        /** @var GymUser $user */
        $user = $request->user();
        $plan = $user->gym->plans()->create($request->validate($this->rules($user->gym_id)));

        return JsonResource::make($plan)->response()->setStatusCode(201);
    }

    /**
     * Price changes only affect new subscriptions: existing ones keep their price snapshot.
     */
    public function update(Request $request, int $plan): JsonResource
    {
        /** @var GymUser $user */
        $user = $request->user();
        $plan = $user->gym->plans()->findOrFail($plan);
        $plan->update($request->validate($this->rules($user->gym_id, $plan->id)));

        return JsonResource::make($plan);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(int $gymId, ?int $ignorePlanId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('gym_plans', 'name')->where('gym_id', $gymId)->ignore($ignorePlanId)],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
            'billing_interval_months' => ['required', 'integer', Rule::in(GymPlan::BILLING_INTERVALS)],
            'grace_days' => ['sometimes', 'integer', 'min:0', 'max:60'],
            'status' => ['sometimes', Rule::in(['ativo', 'inativo'])],
        ];
    }
}
