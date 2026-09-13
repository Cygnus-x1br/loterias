<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class GenerateMissingShareCodesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:generate-share-codes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gera código de compartilhamento (share_code) para usuários que ainda não possuem';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $usersWithoutCode = User::whereNull('share_code')
            ->orWhere('share_code', '')
            ->get();

        if ($usersWithoutCode->isEmpty()) {
            $this->info('Todos os usuários já possuem código de compartilhamento!');

            return Command::SUCCESS;
        }

        $count = 0;
        foreach ($usersWithoutCode as $user) {
            $user->share_code = User::generateUniqueShareCode();
            $user->saveQuietly();
            $count++;
        }

        $this->info("Sucesso: Códigos gerados para {$count} usuário(s)!");

        return Command::SUCCESS;
    }
}
