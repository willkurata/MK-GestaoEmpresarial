<?php

namespace App\Http\Controllers;

use App\Actions\CompanyUsers\ResetCompanyUserPassword;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets a company Administrator manage the individual accounts of their own company.
 * Every query is scoped to the signed-in company; ids from other companies resolve to 404.
 */
class CompanyUsersController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Company $company */
        $company = $request->user('company');

        return Inertia::render('company/Users/Index', [
            'users' => $company->companyUsers()
                ->select(['id', 'cpf', 'name', 'username', 'role', 'status', 'created_at'])
                ->orderBy('name')
                ->get(),
            'roles' => CompanyUser::ROLES,
            'currentUserId' => $request->user('company_user')->id,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var Company $company */
        $company = $request->user('company');
        $request->merge([
            'cpf' => preg_replace('/\D+/', '', (string) $request->input('cpf')),
            'username' => trim((string) $request->input('username')),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'cpf' => [
                'required', 'digits:11',
                Rule::unique('company_users', 'cpf')->where('company_id', $company->id),
            ],
            'username' => [
                'required', 'string', 'max:255',
                Rule::unique('company_users', 'username')->where('company_id', $company->id),
            ],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            'role' => ['required', Rule::in(CompanyUser::ROLES)],
        ]);

        DB::transaction(function () use ($company, $request, $validated): void {
            $companyUser = $company->companyUsers()->create([
                ...$validated,
                'name' => trim($validated['name']),
                'status' => 'ativa',
            ]);

            AuditLog::record('company_user.created', $request->user('company_user'), $company->id, $companyUser, [
                'username' => $companyUser->username,
                'role' => $companyUser->role,
            ]);
        });

        return back()->with('success', 'Usuário cadastrado.');
    }

    public function update(Request $request, int $companyUser): RedirectResponse
    {
        /** @var Company $company */
        $company = $request->user('company');
        /** @var CompanyUser $actor */
        $actor = $request->user('company_user');
        $user = $company->companyUsers()->findOrFail($companyUser);
        $request->merge(['username' => trim((string) $request->input('username'))]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required', 'string', 'max:255',
                Rule::unique('company_users', 'username')->where('company_id', $company->id)->ignore($user->id),
            ],
            'role' => ['required', Rule::in(CompanyUser::ROLES)],
            'status' => ['required', Rule::in(['ativa', 'inativa'])],
        ]);

        if ($user->is($actor) && ($validated['role'] !== $user->role || $validated['status'] !== $user->status)) {
            throw ValidationException::withMessages([
                'role' => 'Você não pode alterar o próprio perfil ou status. Peça a outro Administrador.',
            ]);
        }

        DB::transaction(function () use ($company, $actor, $user, $validated): void {
            $company->newQuery()->whereKey($company->id)->lockForUpdate()->firstOrFail();
            $user->refresh();

            $removesAdministrator = $validated['role'] !== 'Administrador' || $validated['status'] !== 'ativa';
            if ($removesAdministrator && $user->isLastActiveAdministrator()) {
                throw ValidationException::withMessages([
                    'role' => 'A empresa precisa manter pelo menos um Administrador ativo.',
                ]);
            }

            $user->fill([
                ...$validated,
                'name' => trim($validated['name']),
            ]);
            $changes = $this->auditableChanges($user);
            $user->save();

            if ($changes !== []) {
                AuditLog::record('company_user.updated', $actor, $company->id, $user, ['changes' => $changes]);
            }
        });

        return back()->with('success', 'Usuário atualizado.');
    }

    public function resetPassword(Request $request, int $companyUser, ResetCompanyUserPassword $resetPassword): RedirectResponse
    {
        /** @var Company $company */
        $company = $request->user('company');
        /** @var CompanyUser $actor */
        $actor = $request->user('company_user');
        $user = $company->companyUsers()->findOrFail($companyUser);

        if ($user->is($actor)) {
            throw ValidationException::withMessages([
                'password' => 'Para trocar a sua própria senha, use a página Minha senha.',
            ]);
        }

        return back()
            ->with('success', 'Senha temporária gerada. Entregue ao usuário por um canal privado.')
            ->with('temporaryPassword', $resetPassword->handle($user, $actor, 'company_admin'));
    }

    /**
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function auditableChanges(CompanyUser $user): array
    {
        return collect($user->getDirty())
            ->only(['name', 'username', 'role', 'status'])
            ->map(fn (mixed $value, string $attribute): array => [
                'from' => $user->getOriginal($attribute),
                'to' => $value,
            ])
            ->all();
    }
}
