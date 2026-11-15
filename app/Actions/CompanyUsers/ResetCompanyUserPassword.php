<?php

namespace App\Actions\CompanyUsers;

use App\Models\AuditLog;
use App\Models\CompanyUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ResetCompanyUserPassword
{
    /**
     * Replace the user's password with a one-time temporary password that must be
     * changed on the next sign-in. Existing sessions and remember-me tokens stop working.
     *
     * @return array{name: string, username: string, password: string}
     */
    public function handle(CompanyUser $companyUser, Model $resetBy, string $resetSource): array
    {
        $temporaryPassword = Str::password(18, symbols: false);

        DB::transaction(function () use ($companyUser, $resetBy, $resetSource, $temporaryPassword): void {
            $companyUser->forceFill([
                'password' => $temporaryPassword,
                'must_change_password' => true,
                'remember_token' => Str::random(60),
            ])->save();

            AuditLog::record('company_user.password_reset', $resetBy, $companyUser->company_id, $companyUser, [
                'source' => $resetSource,
                'username' => $companyUser->username,
            ]);
        });

        return [
            'name' => $companyUser->name,
            'username' => $companyUser->username,
            'password' => $temporaryPassword,
        ];
    }
}
