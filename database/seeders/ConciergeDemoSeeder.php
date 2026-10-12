<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Client;
use App\Models\Concierge;
use App\Models\Incident;
use App\Models\Membership;
use App\Models\Message;
use App\Models\Mission;
use App\Models\Property;
use App\Models\ServiceTemplate;
use App\Models\Stay;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConciergeDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new \RuntimeException('Les données de démonstration sont réservées à l’environnement local.');
        }
        if (User::where('email', 'demo@conciergerie.test')->exists()) {
            $this->command->info('La démonstration existe déjà ; aucun compte modifié.');

            return;
        }
        $password = Str::password(18);
        DB::transaction(function () use ($password) {
            $concierge = Concierge::create(['name' => 'Les Clés du Lac · Démonstration']);
            $manager = User::create(['name' => 'Chloé Bernard', 'email' => 'demo@conciergerie.test', 'password' => $password]);
            $manager->forceFill(['email_verified_at' => now()])->save();
            Membership::create(['concierge_id' => $concierge->id, 'user_id' => $manager->id, 'role' => 'manager', 'team' => 'Annecy']);
            $worker = User::create(['name' => 'Thomas Martin', 'email' => 'terrain@conciergerie.test', 'password' => $password]);
            $worker->forceFill(['email_verified_at' => now()])->save();
            Membership::create(['concierge_id' => $concierge->id, 'user_id' => $worker->id, 'role' => 'worker', 'team' => 'Annecy']);
            $client = Client::create(['concierge_id' => $concierge->id, 'name' => 'Camille Laurent', 'email' => 'client@conciergerie.test', 'phone' => '', 'kind' => 'individual']);
            $clientUser = User::create(['name' => 'Camille Laurent', 'email' => 'client@conciergerie.test', 'password' => $password]);
            $clientUser->forceFill(['email_verified_at' => now()])->save();
            Membership::create(['concierge_id' => $concierge->id, 'user_id' => $clientUser->id, 'role' => 'client', 'client_id' => $client->id]);
            $templates = [];
            foreach (['Entretien de résidence' => ['Vérifier l’état général', 'Nettoyer les surfaces', 'Contrôler les équipements', 'Prendre les photos avant / après'], 'Visite de surveillance' => ['Vérifier les accès', 'Contrôler les équipements', 'Relever les anomalies'], 'Constat du bien' => ['Observer chaque pièce', 'Vérifier l’inventaire', 'Photographier les points de contrôle']] as $name => $checklist) {
                $templates[] = ServiceTemplate::create(['concierge_id' => $concierge->id, 'name' => $name, 'checklist' => $checklist]);
            }
            $places = [];
            foreach ([['Villa des Pins', 'Cap Ferret', 'rental'], ['Les Alizés', 'Annecy', 'residence'], ['Résidence du Lac', 'Annecy', 'residence']] as $place) {
                $places[] = Property::create(['concierge_id' => $concierge->id, 'client_id' => $client->id, 'name' => $place[0], 'address' => $place[1].' · Adresse de démonstration', 'kind' => $place[2], 'instructions' => 'Données fictives. Vérifier les équipements et fermer les accès en partant.', 'access_code' => 'DEMO-1234', 'offline_access' => true]);
            }
            foreach (['09:00', '11:30', '15:00'] as $index => $time) {
                $mission = Mission::create(['concierge_id' => $concierge->id, 'property_id' => $places[$index]->id, 'assigned_user_id' => $index === 0 ? $manager->id : $worker->id, 'title' => $templates[$index]->name, 'scheduled_at' => now()->setTimeFromTimeString($time), 'duration' => $index === 0 ? 120 : 60, 'checklist' => $templates[$index]->checklist, 'status' => $index === 1 ? 'in_progress' : ($index === 2 ? 'accepted' : 'planned')]);
                Activity::create(['concierge_id' => $concierge->id, 'user_id' => $manager->id, 'mission_id' => $mission->id, 'description' => 'Mission de démonstration planifiée : '.$mission->title]);
                if ($index === 1) {
                    Incident::create(['concierge_id' => $concierge->id, 'mission_id' => $mission->id, 'author_id' => $worker->id, 'operation_id' => (string) Str::uuid(), 'description' => 'Exemple : poignée de fenêtre à vérifier.']);
                }
            }
            Stay::create(['concierge_id' => $concierge->id, 'property_id' => $places[0]->id, 'title' => 'Séjour de démonstration', 'starts_on' => now()->toDateString(), 'ends_on' => now()->addDays(4)->toDateString(), 'source' => 'manual', 'kind' => 'stay']);
            Message::create(['concierge_id' => $concierge->id, 'client_id' => $client->id, 'author_id' => $clientUser->id, 'body' => 'Bonjour, pouvez-vous prévoir un contrôle de la résidence cette semaine ? (Démonstration)']);
        });
        $this->command->info('Comptes locaux : demo@conciergerie.test / terrain@conciergerie.test / client@conciergerie.test');
        $this->command->info('Mot de passe initial commun : '.$password);
    }
}
