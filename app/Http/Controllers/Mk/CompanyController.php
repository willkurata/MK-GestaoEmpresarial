<?php

namespace App\Http\Controllers\Mk;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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

        $company = DB::transaction(function () use ($validated): Company {
            $company = Company::query()->create([
                'name' => trim($validated['name']),
                'cnpj' => $validated['cnpj'],
                'password' => $validated['company_password'],
                'status' => 'ativa',
            ]);

            $company->companyUsers()->create([
                'name' => trim($validated['admin_name']),
                'cpf' => $validated['admin_cpf'],
                'username' => trim($validated['admin_username']),
                'password' => $validated['admin_password'],
                'role' => 'Administrador',
                'status' => 'ativa',
            ]);

            return $company;
        });

        return redirect()->route('mk.companies.show', $company)->with('success', 'Empresa e usuário administrador cadastrados.');
    }

    public function show(Company $company): Response
    {
        return Inertia::render('mk/Companies/Show', [
            'company' => $company->only(['id', 'name', 'cnpj', 'status', 'created_at']),
            'users' => $company->companyUsers()
                ->select(['id', 'company_id', 'cpf', 'name', 'username', 'role', 'status', 'created_at'])
                ->orderBy('name')
                ->get(),
            'roles' => CompanyUser::ROLES,
        ]);
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['ativa', 'inativa'])],
        ]);

        $company->update($validated);

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

        $company->companyUsers()->create([
            ...$validated,
            'name' => trim($validated['name']),
            'username' => trim($validated['username']),
            'status' => 'ativa',
        ]);

        return back()->with('success', 'Usuário cadastrado para esta empresa.');
    }

    public function updateUser(Request $request, Company $company, CompanyUser $companyUser): RedirectResponse
    {
        $user = $company->companyUsers()->findOrFail($companyUser->getKey());
        $validated = $request->validate([
            'role' => ['required', Rule::in(CompanyUser::ROLES)],
            'status' => ['required', Rule::in(['ativa', 'inativa'])],
        ]);

        $user->update($validated);

        return back()->with('success', 'Acesso do usuário atualizado.');
    }
}
