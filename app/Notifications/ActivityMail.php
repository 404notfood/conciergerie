<?php

namespace App\Notifications;

use App\Models\BusinessNotification;
use App\Models\Membership;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ActivityMail extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $activityId)
    {
        $this->onQueue('activity-mail');
    }

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function shouldSend(User $notifiable, string $channel): bool
    {
        $record = BusinessNotification::whereKey($this->activityId)->where('user_id', $notifiable->id)->first();
        if (! $record || $notifiable->fresh()?->disabled_at || ! Membership::where('concierge_id', $record->concierge_id)->where('user_id', $notifiable->id)->exists()) {
            return false;
        }
        $preferences = NotificationPreference::where('concierge_id', $record->concierge_id)->where('user_id', $notifiable->id)->first();

        return in_array($record->event, $preferences->email_events ?? [], true);
    }

    public function toMail(User $notifiable): MailMessage
    {
        $record = BusinessNotification::whereKey($this->activityId)->where('user_id', $notifiable->id)->firstOrFail();

        return (new MailMessage)->subject($record->title)->greeting('Bonjour,')->line($record->body)->action('Consulter mon espace', route('notifications.open', $record->id))->line('Connectez-vous pour consulter les informations autorisées.')->salutation('Votre plateforme de conciergerie');
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120, 600];
    }
}
