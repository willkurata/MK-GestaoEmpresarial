<?php

namespace App\Console\Commands;

use App\Actions\Gym\GenerateDueInvoices;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('academia:generate-invoices')]
#[Description('Gera as mensalidades recorrentes do App Academia que vencem dentro do prazo configurado.')]
class GenerateGymInvoices extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(GenerateDueInvoices $generateDueInvoices): int
    {
        $created = $generateDueInvoices->handle();
        $this->info("{$created} mensalidade(s) gerada(s).");

        return self::SUCCESS;
    }
}
