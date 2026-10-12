<?php

namespace App\Console\Commands;

use App\Actions\Concierge\NotifyActivity;
use App\Actions\Concierge\SubscriptionManager;
use App\Models\Membership;
use App\Models\Mission;
use Illuminate\Console\Command;

class RemindPendingMissions extends Command
{
    protected $signature = 'app:remind-missions';

    protected $description = 'Rappelle les missions proches qui attendent une acceptation, une fois par journée.';

    public function handle(NotifyActivity $notifications, SubscriptionManager $subscriptions): int
    {
        $count = 0;
        Mission::where('status', 'planned')->where('scheduled_at', '<=', now()->addDay())->where('scheduled_at', '>=', now()->subDay())->orderBy('id')->chunkById(100, function ($missions) use ($notifications, $subscriptions, &$count) {
            foreach ($missions as $mission) {
                if (! $subscriptions->usable($subscriptions->ensure($mission->concierge_id))) {
                    continue;
                }
                $key = 'reminder:'.$mission->id.':'.now('Europe/Paris')->toDateString();
                $notifications->send($mission->concierge_id, $notifications->managers($mission->concierge_id), 'mission_response', $key, 'Une mission proche attend encore une acceptation ou une affectation.', 'missions');
                if ($mission->assigned_user_id) {
                    $notifications->send($mission->concierge_id, Membership::where('concierge_id', $mission->concierge_id)->where('user_id', $mission->assigned_user_id)->get(), 'mission_assigned', $key, 'Une mission proche attend votre acceptation.', 'missions');
                }
                $count++;
            }
        });
        $this->info($count.' mission(s) vérifiée(s).');

        return self::SUCCESS;
    }
}
