<?php

namespace Database\Seeders;

use App\Actions\Concierge\SubscriptionManager;
use App\Models\Concierge;
use Illuminate\Database\Seeder;

class ApplicationManagementSeeder extends Seeder
{
    public function run(SubscriptionManager $subscriptions): void
    {
        $subscriptions->settings();
        Concierge::orderBy('id')->each(fn (Concierge $concierge) => $subscriptions->ensure($concierge->id));
    }
}
