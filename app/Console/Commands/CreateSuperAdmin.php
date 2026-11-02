<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

#[Signature('mk:create-super-admin')]
#[Description('Create the initial MK master administrator.')]
class CreateSuperAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = trim((string) $this->ask('Nome do administrador MK'));
        $email = mb_strtolower(trim((string) $this->ask('E-mail do administrador MK')));

        $validator = Validator::make(
            ['name' => $name, 'email' => $email],
            ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', 'unique:users,email']],
        );

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        $password = (string) $this->secret('Senha (mínimo 12 caracteres)');
        $confirmation = (string) $this->secret('Confirme a senha');

        if (mb_strlen($password) < 12 || $password !== $confirmation) {
            $this->error('A senha deve ter pelo menos 12 caracteres e corresponder à confirmação.');

            return self::FAILURE;
        }

        $user = new User;
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'email_verified_at' => now(),
        ])->save();

        $this->info("Administrador master MK {$email} criado.");

        return self::SUCCESS;
    }
}
