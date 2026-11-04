<?php

namespace App\Console\Commands;

use App\Actions\Concierge\SyncCalendarFeed;
use App\Models\CalendarFeed;
use App\Models\ConciergeSubscription;
use Illuminate\Console\Command;

class SyncCalendars extends Command
{
    protected $signature = 'app:sync-calendars';

    protected $description = 'Synchronise les calendriers iCal actifs sans modifier les missions existantes.';

    public function handle(SyncCalendarFeed $sync): int
    {
        $count = 0;
        $failed = 0;
        $deadline = microtime(true) + 50;
        $feeds = CalendarFeed::where('is_active', true)
            ->whereIn('concierge_id', ConciergeSubscription::select('concierge_id')
                ->whereIn('status', ['active', 'trialing'])
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->where(fn ($query) => $query->where('feature_overrides->calendar_import', true)
                    ->orWhere(fn ($query) => $query->whereNull('feature_overrides->calendar_import')->where('terms->features->calendar_import', true))))
            ->whereHas('property', fn ($query) => $query->whereNull('archived_at'))
            ->where(fn ($query) => $query->whereNull('last_attempt_at')->orWhere('last_attempt_at', '<=', now()->subMinutes(15)))
            ->orderByRaw('last_attempt_at IS NOT NULL')->orderBy('last_attempt_at')->orderBy('id')->limit(100)->get();
        foreach ($feeds as $feed) {
            if (microtime(true) >= $deadline) {
                break;
            }
            if (! $sync->eligible($feed)) {
                continue;
            }
            if ($sync->handle($feed)) {
                $count++;
            } else {
                $failed++;
            }
        }
        $this->info($count.' calendrier(s) synchronisé(s), '.$failed.' en échec ou déjà en cours.');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
