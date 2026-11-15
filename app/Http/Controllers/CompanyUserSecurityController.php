<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CompanyUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class CompanyUserSecurityController extends Controller
{
    public function edit(Request $request): Response
    {
        /** @var CompanyUser $companyUser */
        $companyUser = $request->user('company_user');

        return Inertia::render('company/Security', [
            'mustChangePassword' => $companyUser->must_change_password,
            'passwordResetBy' => $companyUser->must_change_password
                ? $this->latestPasswordResetSource($companyUser)
                : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string', 'current_password:company_user'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed', 'different:current_password'],
        ]);

        /** @var CompanyUser $companyUser */
        $companyUser = $request->user('company_user');
        $companyUser->update([
            'password' => $validated['password'],
            'must_change_password' => false,
        ]);
        $request->session()->put(CompanyUser::PASSWORD_FINGERPRINT_SESSION_KEY, $companyUser->passwordFingerprint());

        AuditLog::record('company_user.password_changed', $companyUser, $companyUser->company_id, $companyUser);

        return back()->with('success', 'Senha atualizada com sucesso.');
    }

    /**
     * Who issued the temporary password the user must replace: "mk" or "company_admin".
     */
    private function latestPasswordResetSource(CompanyUser $companyUser): ?string
    {
        $metadata = AuditLog::query()
            ->where('company_id', $companyUser->company_id)
            ->where('action', 'company_user.password_reset')
            ->where('subject_type', $companyUser->getMorphClass())
            ->where('subject_id', $companyUser->getKey())
            ->latest('id')
            ->first()
            ?->metadata;
        $source = $metadata['source'] ?? null;

        return is_string($source) ? $source : null;
    }
}
