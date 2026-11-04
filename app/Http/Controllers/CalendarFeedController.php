<?php

namespace App\Http\Controllers;

use App\Actions\Concierge\FetchCalendar;
use App\Actions\Concierge\QuotaGate;
use App\Actions\Concierge\SyncCalendarFeed;
use App\Actions\Concierge\WorkspaceAccess;
use App\Models\CalendarFeed;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CalendarFeedController extends Controller
{
    public function __construct(private WorkspaceAccess $access, private QuotaGate $quotas) {}

    public function store(Request $request, FetchCalendar $fetch, SyncCalendarFeed $sync): RedirectResponse
    {
        $member = $this->access->manager($request);
        $this->quotas->feature($request, 'calendar_import');
        $data = $request->validate(['property_id' => ['required', 'integer', Rule::exists('properties', 'id')->where('concierge_id', $member->concierge_id)->whereNull('archived_at')], 'name' => ['required', 'string', 'max:120'], 'source' => ['required', Rule::in(['airbnb', 'booking', 'ical'])], 'url' => ['required', 'string', 'max:4096']]);
        $data['url'] = $fetch->validateUrl($data['url']);
        try {
            $feed = CalendarFeed::create([...$data, 'concierge_id' => $member->concierge_id, 'is_active' => true]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['url' => 'Ce calendrier est déjà connecté à ce lieu. Modifiez ou réactivez la connexion existante.']);
        }
        $success = $sync->handle($feed);

        return back()->with('success', $success ? 'Calendrier connecté et synchronisé. Les nouvelles périodes restent à qualifier.' : 'Calendrier enregistré. La première synchronisation a échoué : consultez son état et vérifiez le lien.');
    }

    public function update(Request $request, int $calendarFeed, FetchCalendar $fetch): RedirectResponse
    {
        $this->access->manager($request);
        $feed = $this->access->record($request, CalendarFeed::class, $calendarFeed);
        $this->quotas->feature($request, 'calendar_import');
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'url' => ['nullable', 'string', 'max:4096'], 'is_active' => ['required', 'boolean']]);
        if (! empty($data['url'])) {
            $data['url'] = $fetch->validateUrl($data['url']);
        } else {
            unset($data['url']);
        }
        try {
            DB::transaction(function () use ($feed, $data) {
                $current = CalendarFeed::whereKey($feed->id)->lockForUpdate()->firstOrFail();
                $current->fill($data)->forceFill(['last_attempt_at' => null, 'last_error' => null])->save();
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['url' => 'Ce calendrier est déjà connecté à ce lieu.']);
        }

        return back()->with('success', 'Calendrier mis à jour. Les périodes déjà importées sont conservées.');
    }

    public function sync(Request $request, int $calendarFeed, SyncCalendarFeed $sync): RedirectResponse
    {
        $this->access->manager($request);
        $feed = $this->access->record($request, CalendarFeed::class, $calendarFeed);
        $this->quotas->feature($request, 'calendar_import');
        if (! $sync->eligible($feed)) {
            throw ValidationException::withMessages(['calendar' => 'Réactivez le calendrier et son lieu avant de synchroniser.']);
        }
        if ($feed->last_attempt_at?->isAfter(now()->subMinute())) {
            throw ValidationException::withMessages(['calendar' => 'Patientez une minute avant de relancer la synchronisation.']);
        }
        if (! $sync->handle($feed)) {
            throw ValidationException::withMessages(['calendar' => $feed->fresh()->last_error ?? 'Une synchronisation est déjà en cours.']);
        }

        return back()->with('success', 'Calendrier synchronisé. Les nouvelles périodes restent à qualifier ; les missions existantes sont conservées.');
    }
}
