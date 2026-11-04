<?php

namespace App\Actions\Concierge;

use App\Models\CalendarFeed;
use App\Models\ConciergeSubscription;
use App\Models\Stay;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class SyncCalendarFeed
{
    public function __construct(private FetchCalendar $fetch, private ImportCalendar $import, private SubscriptionManager $subscriptions) {}

    public function eligible(CalendarFeed $feed): bool
    {
        $subscription = ConciergeSubscription::where('concierge_id', $feed->concierge_id)->first();

        return $feed->is_active && $feed->property->archived_at === null && $subscription !== null && $this->subscriptions->usable($subscription) && ($this->subscriptions->features($subscription)['calendar_import'] ?? false);
    }

    public function handle(CalendarFeed $feed): bool
    {
        $lock = Cache::lock('calendar-feed:'.$feed->id, 120);
        if (! $lock->get()) {
            return false;
        }
        try {
            $feed->refresh();
            if (! $this->eligible($feed)) {
                return false;
            }
            $feed->forceFill(['last_attempt_at' => now()])->save();
            try {
                $content = $this->fetch->handle($feed->url);
                $events = $this->import->parse($content, allowEmpty: true);
            } catch (Throwable $exception) {
                $message = $exception instanceof ValidationException
                    ? (string) collect($exception->errors())->flatten()->first()
                    : 'Calendrier inaccessible ou trop volumineux. Vérifiez le lien et réessayez. Les dernières périodes valides sont conservées.';
                $feed->forceFill(['last_error' => $message])->save();

                return false;
            }

            return DB::transaction(function () use ($feed, $events) {
                $current = CalendarFeed::whereKey($feed->id)->lockForUpdate()->firstOrFail();
                if (! $this->eligible($current) || $current->url !== $feed->url) {
                    return false;
                }
                $uids = [];
                foreach ($events as $event) {
                    // Namespace the external key to isolate feeds and legacy file imports.
                    $uid = 'feed:'.$feed->id.':'.hash('sha256', $event['uid']);
                    $uids[] = $uid;
                    $stay = Stay::firstOrNew(['concierge_id' => $feed->concierge_id, 'property_id' => $feed->property_id, 'source' => $feed->source, 'external_uid' => $uid, 'calendar_feed_id' => $feed->id]);
                    $stay->fill(['title' => $event['title'], 'starts_on' => $event['start'], 'ends_on' => $event['end']]);
                    if (! $stay->exists) {
                        $stay->kind = 'unclassified';
                    }
                    $stay->forceFill(['archived_at' => null])->save();
                }
                Stay::where('concierge_id', $feed->concierge_id)->where('calendar_feed_id', $feed->id)->whereNotIn('external_uid', $uids)->whereNull('archived_at')->update(['archived_at' => now()]);
                $current->forceFill(['last_synced_at' => now(), 'last_error' => null, 'event_count' => count($events)])->save();

                return true;
            });
        } finally {
            $lock->release();
        }
    }
}
