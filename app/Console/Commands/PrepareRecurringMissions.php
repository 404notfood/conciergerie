<?php

namespace App\Console\Commands;

use App\Actions\Concierge\PrepareRecurringMissions as PrepareAction;
use App\Models\MissionRecurrence;
use Illuminate\Console\Command;

class PrepareRecurringMissions extends Command
{
    protected $signature = 'app:prepare-missions {--days=14 : Horizon de préparation (1 à 90 jours)}';
    protected $description = 'Prépare les propositions récurrentes sans créer de mission exécutée automatiquement.';

    public function handle(PrepareAction $prepare): int
    {
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT);
        if ($days === false || $days < 1 || $days > 90) {
            $this->error('L’horizon doit être compris entre 1 et 90 jours.');

            return self::FAILURE;
        }
        $count = 0;
        foreach (MissionRecurrence::where('is_active', true)->distinct()->pluck('concierge_id') as $tenant) {
            $count += $prepare->handle($tenant, $days);
        }
        $this->info($count.' proposition(s) préparée(s).');

        return self::SUCCESS;
    }
}
