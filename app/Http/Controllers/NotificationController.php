<?php

namespace App\Http\Controllers;

use App\Actions\Concierge\NotifyActivity;
use App\Actions\Concierge\WorkspaceAccess;
use App\Models\BusinessNotification;
use App\Models\Membership;
use App\Models\NotificationPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    public function __construct(private WorkspaceAccess $access) {}

    public function read(Request $request, int $notification): RedirectResponse
    {
        $member = $this->access->membership($request);
        $record = BusinessNotification::where('concierge_id', $member->concierge_id)->where('user_id', $request->user()->id)->findOrFail($notification);
        $record->update(['read_at' => $record->read_at ?? now()]);

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $member = $this->access->membership($request);
        BusinessNotification::where('concierge_id', $member->concierge_id)->where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('success', 'Notifications marquées comme lues.');
    }

    public function preferences(Request $request): RedirectResponse
    {
        $member = $this->access->membership($request);
        $data = $request->validate(['email_events' => ['present', 'array'], 'email_events.*' => ['string', 'distinct', Rule::in(array_keys(NotifyActivity::EVENTS))], 'browser_events' => ['present', 'array'], 'browser_events.*' => ['string', 'distinct', Rule::in(array_keys(NotifyActivity::EVENTS))]]);
        NotificationPreference::updateOrCreate(['concierge_id' => $member->concierge_id, 'user_id' => $request->user()->id], $data);

        return back()->with('success', 'Préférences de notification enregistrées.');
    }

    public function open(Request $request, int $notification): RedirectResponse
    {
        $record = BusinessNotification::where('user_id', $request->user()->id)->findOrFail($notification);
        Membership::where('concierge_id', $record->concierge_id)->where('user_id', $request->user()->id)->firstOrFail();
        $request->session()->put('concierge_id', $record->concierge_id);

        return redirect($record->url);
    }
}
