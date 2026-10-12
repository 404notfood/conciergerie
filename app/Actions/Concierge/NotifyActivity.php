<?php

namespace App\Actions\Concierge;

use App\Models\BusinessNotification;
use App\Models\Membership;
use App\Models\Mission;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\ActivityMail;
use Illuminate\Database\Eloquent\Collection;

class NotifyActivity
{
    public const EVENTS = [
        'mission_assigned' => 'Mission attribuée ou modifiée',
        'mission_response' => 'Acceptation ou réponse attendue',
        'report_submitted' => 'Compte rendu à contrôler',
        'report_reviewed' => 'Validation ou demande de reprise',
        'incident_created' => 'Incident signalé',
        'incident_updated' => 'Suivi d’un incident',
        'recurrence_due' => 'Proposition de mission récurrente',
        'message_received' => 'Nouveau message',
        'client_request' => 'Demande d’intervention',
    ];

    /** @return Collection<int, Membership> */
    public function managers(int $tenant): Collection
    {
        return Membership::where('concierge_id', $tenant)->where('role', 'manager')->get();
    }

    /** @param iterable<int, Membership> $recipients */
    public function send(int $tenant, iterable $recipients, string $event, string $key, string $body, string $view, ?int $actorId = null): void
    {
        foreach ($recipients as $member) {
            if ($member->user_id === $actorId || $member->concierge_id !== $tenant) {
                continue;
            }
            $user = User::whereKey($member->user_id)->whereNull('disabled_at')->first();
            if (! $user) {
                continue;
            }
            $record = BusinessNotification::firstOrCreate(['concierge_id' => $tenant, 'user_id' => $user->id, 'dedupe_key' => hash('sha256', $event.':'.$key)], ['event' => $event, 'title' => self::EVENTS[$event], 'body' => $body, 'url' => '/dashboard?view='.$view]);
            $preferences = NotificationPreference::where('concierge_id', $tenant)->where('user_id', $user->id)->first();
            if ($record->wasRecentlyCreated && in_array($event, $preferences->email_events ?? [], true)) {
                $user->notify((new ActivityMail($record->id))->afterCommit());
            }
        }
    }

    public function assigned(Mission $mission, ?int $actorId = null): void
    {
        $query = Membership::where('concierge_id', $mission->concierge_id);
        if ($mission->assigned_user_id) {
            $query->where('user_id', $mission->assigned_user_id);
        } elseif ($mission->assigned_company) {
            $query->where('role', 'coordinator')->where('company', $mission->assigned_company);
        } else {
            return;
        }
        $this->send($mission->concierge_id, $query->get(), 'mission_assigned', 'mission:'.$mission->id.':'.$mission->version, 'Une mission attend votre consultation dans le planning.', 'missions', $actorId);
    }

    /** @return array<string, mixed> */
    public function inbox(int $tenant, int $userId): array
    {
        $query = BusinessNotification::where('concierge_id', $tenant)->where('user_id', $userId);
        $preferences = NotificationPreference::where('concierge_id', $tenant)->where('user_id', $userId)->first();

        return ['items' => (clone $query)->latest('id')->limit(100)->get(), 'unread' => (clone $query)->whereNull('read_at')->count(), 'preferences' => ['email_events' => $preferences->email_events ?? [], 'browser_events' => $preferences->browser_events ?? []], 'eventLabels' => self::EVENTS];
    }
}
