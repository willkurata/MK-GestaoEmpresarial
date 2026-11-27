<?php

namespace App\Http\Controllers\Mk;

use App\Actions\Cashier\ListOpenCashSessions;
use App\Actions\Companies\BuildCompanyDashboard;
use App\Actions\CompanyUsers\ResetCompanyUserPassword;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('mk/Companies/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'cnpj' => preg_replace('/\D+/', '', (string) $request->input('cnpj')),
            'admin_cpf' => preg_replace('/\D+/', '', (string) $request->input('admin_cpf')),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'cnpj' => ['required', 'digits:14', 'unique:companies,cnpj'],
            'company_password' => ['required', 'string', 'min:12', 'confirmed'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_cpf' => ['required', 'digits:11'],
            'admin_username' => ['required', 'string', 'max:255'],
            'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $company = DB::transaction(function () use ($request, $validated): Company {
            $company = Company::query()->create([
                'name' => trim($validated['name']),
                'cnpj' => $validated['cnpj'],
                'password' => $validated['company_password'],
                'status' => 'ativa',
            ]);

            $administrator = $company->companyUsers()->create([
                'name' => trim($validated['admin_name']),
                'cpf' => $validated['admin_cpf'],
                'username' => trim($validated['admin_username']),
                'password' => $validated['admin_password'],
                'role' => 'Administrador',
                'status' => 'ativa',
            ]);

            AuditLog::record('company.created', $request->user(), $company->id, $company, ['cnpj' => $company->cnpj]);
            AuditLog::record('company_user.created', $request->user(), $company->id, $administrator, [
                'source' => 'mk',
                'username' => $administrator->username,
                'role' => $administrator->role,
            ]);

            return $company;
        });

        return redirect()->route('mk.companies.show', $company)->with('success', 'Empresa e usuário administrador cadastrados.');
    }

    public function show(Request $request, Company $company, BuildCompanyDashboard $buildDashboard, ListOpenCashSessions $listOpenCashSessions): Response
    {
        $filters = $request->validate([
            'period' => ['nullable', Rule::in(BuildCompanyDashboard::PERIODS)],
            'start_date' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d'],
            'end_date' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        return Inertia::render('mk/Companies/Show', [
            'dashboard' => fn (): array => $buildDashboard->handle($company, $filters),
            'company' => $company->only(['id', 'name', 'cnpj', 'status', 'created_at']),
            'users' => $company->companyUsers()
                ->select(['id', 'company_id', 'cpf', 'name', 'username', 'role', 'status', 'created_at'])
                ->orderBy('name')
                ->get(),
            'roles' => CompanyUser::ROLES,
            'openCashSessions' => $listOpenCashSessions->forCompany($company),
        ]);
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['ativa', 'inativa'])],
        ]);

        $previousStatus = $company->status;
        $company->update($validated);

        if ($previousStatus !== $company->status) {
            AuditLog::record('company.status_updated', $request->user(), $company->id, $company, [
                'from' => $previousStatus,
                'to' => $company->status,
            ]);
        }

        return back()->with('success', 'Status da empresa atualizado.');
    }

    public function storeUser(Request $request, Company $company): RedirectResponse
    {
        $request->merge([
            'cpf' => preg_replace('/\D+/', '', (string) $request->input('cpf')),
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
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(CompanyUser::ROLES)],
        ]);

        $companyUser = $company->companyUsers()->create([
            ...$validated,
            'name' => trim($validated['name']),
            'username' => trim($validated['username']),
            'status' => 'ativa',
        ]);

        AuditLog::record('company_user.created', $request->user(), $company->id, $companyUser, [
            'source' => 'mk',
            'username' => $companyUser->username,
            'role' => $companyUser->role,
        ]);

        return back()->with('success', 'Usuário cadastrado para esta empresa.');
    }

    public function updateUser(Request $request, Company $company, CompanyUser $companyUser): RedirectResponse
    {
        $user = $company->companyUsers()->whereKey($companyUser->getKey())->firstOrFail();
        $validated = $request->validate([
            'role' => ['required', Rule::in(CompanyUser::ROLES)],
            'status' => ['required', Rule::in(['ativa', 'inativa'])],
        ]);

        DB::transaction(function () use ($request, $company, $user, $validated): void {
            $company->newQuery()->whereKey($company->id)->lockForUpdate()->firstOrFail();
            $user->refresh();

            $removesAdministrator = $validated['role'] !== 'Administrador' || $validated['status'] !== 'ativa';
            if ($removesAdministrator && $user->isLastActiveAdministrator()) {
                throw ValidationException::withMessages([
                    'role' => 'A empresa precisa manter pelo menos um Administrador ativo.',
                ]);
            }

            $user->fill($validated);
            $changes = collect($user->getDirty())
                ->only(['role', 'status'])
                ->map(fn (mixed $value, string $attribute): array => ['from' => $user->getOriginal($attribute), 'to' => $value])
                ->all();
            $user->save();

            if ($changes !== []) {
                AuditLog::record('company_user.updated', $request->user(), $company->id, $user, [
                    'source' => 'mk',
                    'changes' => $changes,
                ]);
            }
        });

        return back()->with('success', 'Acesso do usuário atualizado.');
    }

    public function resetUserPassword(
        Request $request,
        Company $company,
        CompanyUser $companyUser,
        ResetCompanyUserPassword $resetPassword,
    ): RedirectResponse {
        $user = $company->companyUsers()->whereKey($companyUser->getKey())->firstOrFail();

        abort_unless($user->role === 'Administrador', 404);

        return back()
            ->with('success', 'Senha temporária gerada. Compartilhe com o Administrador por um canal privado.')
            ->with('temporaryPassword', $resetPassword->handle($user, $request->user(), 'mk'));
    }
}
