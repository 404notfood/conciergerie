<?php

namespace App\Actions\Concierge;

use App\Models\Concierge;
use App\Models\MissionRecurrence;
use App\Models\RecurrenceOccurrence;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PrepareRecurringMissions
{
    public function __construct(private SubscriptionManager $subscriptions, private NotifyActivity $notifications) {}

    public function handle(int $tenant, int $days = 14): int
    {
        $until = now('Europe/Paris')->startOfDay()->addDays($days);
        $count = 0;
        MissionRecurrence::where('concierge_id', $tenant)->where('is_active', true)->where('next_on', '<=', $until->toDateString())->orderBy('id')->chunkById(100, function ($rules) use ($until, &$count, $tenant) {
            foreach ($rules as $candidate) {
                $count += DB::transaction(function () use ($candidate, $until, $tenant) {
                    Concierge::whereKey($tenant)->lockForUpdate()->firstOrFail();
                    $rule = MissionRecurrence::whereKey($candidate->id)->lockForUpdate()->firstOrFail();
                    if (! $rule->is_active || ! $this->subscriptions->usable($this->subscriptions->ensure($tenant))) {
                        return 0;
                    }
                    $created = 0;
                    $day = $rule->next_on->copy()->startOfDay();
                    $template = $rule->template;
                    for ($i = 0; $i < 200 && $day->toDateString() <= $until->toDateString(); $i++) {
                        if ($rule->ends_on && $day->gt($rule->ends_on)) {
                            $rule->is_active = false;
                            break;
                        }
                        $snapshot = ['property_id' => $rule->property_id, 'assigned_user_id' => $rule->assigned_user_id, 'title' => $rule->title, 'kind' => $rule->kind, 'scheduled_at' => Carbon::parse($day->toDateString().' '.$rule->time, 'Europe/Paris')->utc()->toIso8601String(), 'duration' => $rule->duration, 'instructions' => $rule->instructions, 'checklist' => $template->checklist];
                        $occurrence = RecurrenceOccurrence::firstOrCreate(['mission_recurrence_id' => $rule->id, 'scheduled_on' => $day->toDateString()], ['concierge_id' => $tenant, 'snapshot' => $snapshot]);
                        if ($occurrence->wasRecentlyCreated) {
                            $created++;
                            $this->notifications->send($tenant, $this->notifications->managers($tenant), 'recurrence_due', 'occurrence:'.$occurrence->id, 'Une intervention récurrente attend votre confirmation.', 'recurrences');
                        }
                        $day = $this->next($rule, $day);
                    }
                    $rule->update(['next_on' => $day->toDateString(), 'is_active' => $rule->is_active]);

                    return $created;
                });
            }
        });

        return $count;
    }

    public function next(MissionRecurrence $rule, Carbon $day): Carbon
    {
        if ($rule->frequency === 'monthly') {
            $next = $day->copy()->startOfMonth()->addMonths($rule->interval);

            return $next->day(min($rule->starts_on->day, $next->daysInMonth));
        }

        return $rule->frequency === 'weekly' ? $day->copy()->addWeeks($rule->interval) : $day->copy()->addDays($rule->interval);
    }
}
