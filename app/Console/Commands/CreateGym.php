<?php

namespace App\Console\Commands;

use App\Models\Gym\Gym;
use App\Models\Gym\GymUser;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

#[Signature('academia:create-gym')]
#[Description('Cadastra uma academia (tenant), sua primeira unidade e o primeiro administrador.')]
class CreateGym extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $data = [
            'gym_name' => trim((string) $this->ask('Nome da academia')),
            'unit_name' => trim((string) $this->ask('Nome da primeira unidade', 'Matriz')),
            'admin_name' => trim((string) $this->ask('Nome do administrador')),
            'admin_email' => mb_strtolower(trim((string) $this->ask('E-mail do administrador'))),
        ];
        $data['slug'] = Str::slug($data['gym_name']);

        $validator = Validator::make($data, [
            'gym_name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'unique:gyms,slug'],
            'unit_name' => ['required', 'string', 'max:255'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
        ], ['slug.unique' => 'Já existe uma academia com este identificador.']);

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        $password = (string) $this->secret('Senha do administrador (mínimo 12 caracteres)');
        $confirmation = (string) $this->secret('Confirme a senha');

        if (mb_strlen($password) < 12 || $password !== $confirmation) {
            $this->error('A senha deve ter pelo menos 12 caracteres e corresponder à confirmação.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($data, $password): void {
            $gym = Gym::create(['name' => $data['gym_name'], 'slug' => $data['slug'], 'status' => 'ativa']);
            $gym->units()->create(['name' => $data['unit_name']]);
            $gym->users()->create([
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => $password,
                'role' => GymUser::ROLE_ADMINISTRATOR,
            ]);
        });

        $this->info("Academia criada. Identificador para login: {$data['slug']}");

        return self::SUCCESS;
    }
}
