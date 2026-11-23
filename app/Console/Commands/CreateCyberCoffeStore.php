<?php

namespace App\Console\Commands;

use App\Models\CyberCoffe\Store;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

#[Signature('cybercoffe:create-store')]
#[Description('Create a CyberCoffe store with its first manager account.')]
class CreateCyberCoffeStore extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $data = [
            'code' => mb_strtolower(trim((string) $this->ask('Código da loja (usado no login, ex.: centro)'))),
            'name' => trim((string) $this->ask('Nome da loja')),
            'manager_name' => trim((string) $this->ask('Nome do gerente')),
            'username' => trim((string) $this->ask('Usuário do gerente')),
        ];

        $validator = Validator::make($data, [
            'code' => ['required', 'alpha_dash', 'max:40', 'unique:cc_stores,code'],
            'name' => ['required', 'string', 'max:255'],
            'manager_name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'alpha_dash', 'max:60'],
        ]);

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        $password = (string) $this->secret('Senha do gerente (mínimo 12 caracteres)');
        $confirmation = (string) $this->secret('Confirme a senha');

        if (mb_strlen($password) < 12 || $password !== $confirmation) {
            $this->error('A senha deve ter pelo menos 12 caracteres e corresponder à confirmação.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($data, $password): void {
            $store = Store::create(['code' => $data['code'], 'name' => $data['name'], 'status' => 'ativa']);

            $store->attendants()->create([
                'name' => $data['manager_name'],
                'username' => $data['username'],
                'password' => $password,
                'role' => 'gerente',
            ]);
        });

        $this->info("Loja {$data['code']} criada com o gerente {$data['username']}.");

        return self::SUCCESS;
    }
}
