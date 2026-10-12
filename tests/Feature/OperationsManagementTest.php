<?php

use App\Actions\Concierge\NotifyActivity;
use App\Actions\Concierge\PrepareRecurringMissions;
use App\Actions\Concierge\SubscriptionManager;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\BusinessNotification;
use App\Models\Client;
use App\Models\Concierge;
use App\Models\Incident;
use App\Models\IncidentEvent;
use App\Models\Membership;
use App\Models\Mission;
use App\Models\MissionRecurrence;
use App\Models\NotificationPreference;
use App\Models\Photo;
use App\Models\Property;
use App\Models\RecurrenceOccurrence;
use App\Models\Report;
use App\Models\ServiceTemplate;
use App\Models\StaffAvailability;
use App\Models\User;
use App\Notifications\ActivityMail;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function operationsContext(): array
{
    $tenant = Concierge::factory()->create();
    $manager = User::factory()->create();
    $worker = User::factory()->create();
    $clientUser = User::factory()->create();
    $client = Client::factory()->create(['concierge_id' => $tenant->id]);
    foreach ([[$manager, 'manager', null], [$worker, 'worker', null], [$clientUser, 'client', $client->id]] as [$user, $role, $clientId]) {
        Membership::factory()->create(['concierge_id' => $tenant->id, 'user_id' => $user->id, 'role' => $role, 'client_id' => $clientId]);
    }
    $property = Property::factory()->create(['concierge_id' => $tenant->id, 'client_id' => $client->id, 'name' => 'Résidence des Pins', 'address' => '12 rue des Jardins', 'access_code' => 'SECRET-ACCESS', 'instructions' => 'SECRET-INSTRUCTIONS']);
    $template = ServiceTemplate::factory()->create(['concierge_id' => $tenant->id, 'checklist' => ['Vérifier les équipements', 'Documenter le passage']]);
    $mission = Mission::factory()->create(['concierge_id' => $tenant->id, 'property_id' => $property->id, 'assigned_user_id' => $worker->id, 'scheduled_at' => '2026-10-14 08:00:00', 'duration' => 60, 'status' => 'accepted', 'checklist' => $template->checklist]);
    $subscription = app(SubscriptionManager::class)->ensure($tenant->id);

    return compact('tenant', 'manager', 'worker', 'clientUser', 'client', 'property', 'template', 'mission', 'subscription');
}

function recurrencePayload(array $context, array $overrides = []): array
{
    return [...['property_id' => $context['property']->id, 'template_id' => $context['template']->id, 'assigned_user_id' => $context['worker']->id, 'title' => 'Visite de surveillance', 'kind' => 'service', 'frequency' => 'weekly', 'interval' => 1, 'starts_on' => '2026-10-12', 'ends_on' => null, 'time' => '11:00', 'duration' => 60, 'instructions' => null], ...$overrides];
}

function operationsMissionPayload(array $context, array $overrides = []): array
{
    return [...['property_id' => $context['property']->id, 'template_id' => $context['template']->id, 'assigned_user_id' => $context['worker']->id, 'title' => 'Nouvelle intervention', 'kind' => 'service', 'scheduled_at' => '2026-10-15T10:00', 'duration' => 60], ...$overrides];
}

test('creating a recurrence prepares proposals and confirmation creates exactly one mission', function () {
    $this->travelTo(new DateTimeImmutable('2026-10-12T08:00:00Z'));
    $c = operationsContext();
    $this->actingAs($c['manager'])->post(route('recurrences.store'), recurrencePayload($c))->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseCount('recurrence_occurrences', 3);
    $this->assertDatabaseCount('missions', 1);
    $candidate = RecurrenceOccurrence::orderBy('id')->first();
    $this->post(route('occurrences.decide', $candidate->id), ['decision' => 'confirmed'])->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseHas('recurrence_occurrences', ['id' => $candidate->id, 'status' => 'confirmed']);
    $this->assertDatabaseCount('missions', 2);
    $this->post(route('occurrences.decide', $candidate->id), ['decision' => 'confirmed'])->assertConflict();
    $this->assertDatabaseCount('missions', 2);
});

test('scheduled preparation is idempotent and retains checklist snapshots', function () {
    $this->travelTo(new DateTimeImmutable('2026-10-12T08:00:00Z'));
    $c = operationsContext();
    $this->actingAs($c['manager'])->post(route('recurrences.store'), recurrencePayload($c))->assertSessionHasNoErrors();
    $c['template']->update(['checklist' => ['Nouvelle tâche']]);
    $this->artisan('app:prepare-missions')->assertSuccessful();
    $this->assertDatabaseCount('recurrence_occurrences', 3);
    expect(RecurrenceOccurrence::first()->snapshot['checklist'])->toBe(['Vérifier les équipements', 'Documenter le passage']);
    $this->artisan('app:prepare-missions --days=200')->assertFailed();
});

test('monthly recurrences keep their anchor day across short months', function () {
    $this->travelTo(new DateTimeImmutable('2026-01-01T08:00:00Z'));
    $c = operationsContext();
    $rule = MissionRecurrence::factory()->create(['concierge_id' => $c['tenant']->id, 'property_id' => $c['property']->id, 'template_id' => $c['template']->id, 'starts_on' => '2026-01-31', 'next_on' => '2026-01-31', 'frequency' => 'monthly']);
    app(PrepareRecurringMissions::class)->handle($c['tenant']->id, 90);
    expect(RecurrenceOccurrence::where('mission_recurrence_id', $rule->id)->orderBy('scheduled_on')->get()->map(fn ($o) => $o->scheduled_on->toDateString())->all())->toBe(['2026-01-31', '2026-02-28', '2026-03-31']);
});

test('recurrences preserve the local time through daylight saving changes', function () {
    $this->travelTo(new DateTimeImmutable('2026-03-28T06:00:00Z'));
    $c = operationsContext();
    MissionRecurrence::factory()->create(['concierge_id' => $c['tenant']->id, 'property_id' => $c['property']->id, 'template_id' => $c['template']->id, 'starts_on' => '2026-03-28', 'next_on' => '2026-03-28', 'frequency' => 'daily', 'time' => '09:00']);
    app(PrepareRecurringMissions::class)->handle($c['tenant']->id, 1);
    expect(RecurrenceOccurrence::orderBy('scheduled_on')->get()->map(fn ($o) => $o->snapshot['scheduled_at'])->all())->toBe(['2026-03-28T08:00:00+00:00', '2026-03-29T07:00:00+00:00']);
});

test('suspended or expired recurrence rules do not generate or execute proposals', function () {
    $this->travelTo(new DateTimeImmutable('2026-10-12T08:00:00Z'));
    $c = operationsContext();
    $this->actingAs($c['manager'])->post(route('recurrences.store'), recurrencePayload($c))->assertSessionHasNoErrors();
    $rule = MissionRecurrence::first();
    $this->post(route('recurrences.toggle', $rule->id), ['is_active' => false])->assertRedirect();
    $this->post(route('occurrences.decide', RecurrenceOccurrence::first()->id), ['decision' => 'confirmed'])->assertConflict();
    $this->post(route('occurrences.decide', RecurrenceOccurrence::first()->id), ['decision' => 'skipped'])->assertRedirect();
    $this->assertDatabaseCount('missions', 1);
    $rule->update(['is_active' => true]);
    $c['subscription']->update(['status' => 'suspended']);
    expect(app(PrepareRecurringMissions::class)->handle($c['tenant']->id, 40))->toBe(0);
});

test('recurrence confirmation respects monthly mission quotas without consuming the proposal', function () {
    $this->travelTo(new DateTimeImmutable('2026-10-12T08:00:00Z'));
    $c = operationsContext();
    $this->actingAs($c['manager'])->post(route('recurrences.store'), recurrencePayload($c))->assertSessionHasNoErrors();
    $c['subscription']->update(['limit_overrides' => ['missions_month' => 1]]);
    $candidate = RecurrenceOccurrence::first();
    $this->post(route('occurrences.decide', $candidate->id), ['decision' => 'confirmed'])->assertSessionHasErrors('subscription');
    expect($candidate->fresh()->status)->toBe('pending');
    $this->assertDatabaseCount('missions', 1);
});

test('recurrence routes enforce tenant and manager boundaries', function () {
    $this->travelTo(new DateTimeImmutable('2026-10-12T08:00:00Z'));
    $c = operationsContext();
    $foreign = MissionRecurrence::factory()->create();
    $this->post(route('recurrences.store'), recurrencePayload($c))->assertRedirect(route('login'));
    $this->actingAs($c['worker'])->post(route('recurrences.store'), recurrencePayload($c))->assertForbidden();
    $this->actingAs($c['manager'])->post(route('recurrences.toggle', $foreign->id), ['is_active' => false])->assertNotFound();
    $this->post(route('recurrences.store'), recurrencePayload($c, ['property_id' => $foreign->property_id]))->assertSessionHasErrors('property_id');
});

test('availability and overlapping missions block new assignments but adjacent slots remain valid', function () {
    $c = operationsContext();
    StaffAvailability::factory()->create(['concierge_id' => $c['tenant']->id, 'user_id' => $c['worker']->id, 'starts_at' => '2026-10-15 08:00:00', 'ends_at' => '2026-10-15 09:00:00']);
    $this->actingAs($c['manager'])->post(route('missions.store'), operationsMissionPayload($c))->assertSessionHasErrors('assigned_user_id');
    $this->post(route('missions.store'), operationsMissionPayload($c, ['scheduled_at' => '2026-10-14T10:30']))->assertSessionHasErrors('assigned_user_id');
    $this->assertDatabaseCount('missions', 1);
    $this->post(route('missions.store'), operationsMissionPayload($c, ['scheduled_at' => '2026-10-15T11:00']))->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseCount('missions', 2);
});

test('workers manage only their own availability and clients never receive staff notes', function () {
    $c = operationsContext();
    $payload = ['user_id' => $c['worker']->id, 'starts_at' => '2026-10-15T10:00', 'ends_at' => '2026-10-15T11:00', 'note' => 'PRIVATE-AVAILABILITY'];
    $this->actingAs($c['worker'])->post(route('availabilities.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
    $this->post(route('availabilities.store'), [...$payload, 'user_id' => $c['manager']->id])->assertForbidden();
    $this->actingAs($c['clientUser'])->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('availabilities', []));
    $this->post(route('availabilities.store'), $payload)->assertForbidden();
    $this->assertDatabaseCount('staff_availabilities', 1);
});

test('notification history is tenant scoped and preferences do not suppress its recording', function () {
    $c = operationsContext();
    $notify = app(NotifyActivity::class);
    $notify->assigned($c['mission'], $c['manager']->id);
    $notify->assigned($c['mission'], $c['manager']->id);
    $this->assertDatabaseCount('business_notifications', 1);
    $entry = BusinessNotification::first();
    $this->actingAs($c['worker'])->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('notificationInbox.unread', 1)->has('notificationInbox.items', 1));
    $this->actingAs($c['manager'])->post(route('notifications.read', $entry->id))->assertNotFound();
    $this->actingAs($c['worker'])->post(route('notifications.read', $entry->id))->assertRedirect();
    expect($entry->fresh()->read_at)->not->toBeNull();
    $foreign = BusinessNotification::factory()->create(['user_id' => $c['worker']->id]);
    $this->post(route('notifications.read', $foreign->id))->assertNotFound();
    $this->assertDatabaseCount('business_notifications', 2);
});

test('mail notifications are opt in and do not bypass membership checks at delivery', function () {
    $c = operationsContext();
    Notification::fake();
    NotificationPreference::factory()->create(['concierge_id' => $c['tenant']->id, 'user_id' => $c['worker']->id, 'email_events' => ['mission_assigned']]);
    app(NotifyActivity::class)->assigned($c['mission'], $c['manager']->id);
    Notification::assertSentTo($c['worker'], ActivityMail::class);
    $notification = new ActivityMail(BusinessNotification::first()->id);
    expect($notification->shouldSend($c['worker'], 'mail'))->toBeTrue();
    $c['mission']->update(['assigned_user_id' => null]);
    Membership::where('concierge_id', $c['tenant']->id)->where('user_id', $c['worker']->id)->delete();
    expect($notification->shouldSend($c['worker'], 'mail'))->toBeFalse();
});

test('notification preferences validate event names and remain individual', function () {
    $c = operationsContext();
    $this->actingAs($c['worker'])->post(route('notifications.preferences'), ['email_events' => ['incident_created'], 'browser_events' => []])->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseHas('notification_preferences', ['user_id' => $c['worker']->id, 'concierge_id' => $c['tenant']->id]);
    $this->post(route('notifications.preferences'), ['email_events' => ['invented'], 'browser_events' => []])->assertSessionHasErrors('email_events.0');
    $this->actingAs($c['manager'])->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('notificationInbox.preferences.email_events', []));
});

test('offline incident retries create a single notification and a single history entry', function () {
    $c = operationsContext();
    $payload = ['operation_id' => (string) Str::uuid(), 'version' => 1, 'description' => 'Une fuite a été constatée.'];
    $this->actingAs($c['worker'])->postJson(route('missions.incident', $c['mission']->id), $payload)->assertOk();
    $this->postJson(route('missions.incident', $c['mission']->id), $payload)->assertOk();
    $this->assertDatabaseCount('incidents', 1);
    $this->assertDatabaseCount('incident_events', 1);
    $this->assertDatabaseCount('business_notifications', 1);
    $this->assertDatabaseHas('business_notifications', ['user_id' => $c['manager']->id, 'event' => 'incident_created']);
});

test('incidents link corrective missions and close only after report validation', function () {
    Storage::fake('local');
    $c = operationsContext();
    $incident = Incident::factory()->create(['concierge_id' => $c['tenant']->id, 'mission_id' => $c['mission']->id, 'author_id' => $c['worker']->id]);
    $this->actingAs($c['manager'])->post(route('incidents.share', $incident->id), ['client_message' => 'Une réparation est prévue.', 'shared_photo_ids' => [], 'status' => 'open'])->assertRedirect();
    $incident->refresh();
    $this->post(route('incidents.repair', $incident->id), ['version' => $incident->version, 'template_id' => $c['template']->id, 'assigned_user_id' => $c['worker']->id, 'scheduled_at' => '2026-10-15T10:00', 'duration' => 60])->assertRedirect()->assertSessionHasNoErrors();
    $incident->refresh();
    $repair = Mission::findOrFail($incident->repair_mission_id);
    $this->post(route('incidents.repair', $incident->id), ['version' => $incident->version, 'template_id' => $c['template']->id, 'scheduled_at' => '2026-10-15T12:00', 'duration' => 60])->assertConflict();
    $this->post(route('incidents.resolve', $incident->id), ['version' => $incident->version, 'resolution_notes' => 'PRIVATE-RESOLUTION', 'resolution_photo_ids' => []])->assertSessionHasErrors('resolution_notes');
    $this->actingAs($c['worker'])->post(route('missions.transition', $repair->id), ['action' => 'accept', 'version' => $repair->version])->assertRedirect();
    $repair->refresh();
    $this->postJson(route('missions.report', $repair->id), ['operation_id' => (string) Str::uuid(), 'version' => $repair->version, 'notes' => 'La fuite est réparée et contrôlée.', 'answers' => [true, true], 'photos' => [UploadedFile::fake()->image('reparation.jpg')]])->assertOk();
    $report = Report::where('mission_id', $repair->id)->first();
    $this->actingAs($c['manager'])->post(route('incidents.share', $incident->id), ['status' => 'in_progress', 'client_message' => 'Information transmise.', 'shared_photo_ids' => [$report->photos()->first()->id]])->assertNotFound();
    $this->post(route('reports.review', $report->id), ['decision' => 'validated'])->assertRedirect();
    $this->post(route('incidents.resolve', $incident->id), ['version' => $incident->version, 'resolution_notes' => 'PRIVATE-RESOLUTION', 'resolution_photo_ids' => [$report->photos()->first()->id]])->assertRedirect()->assertSessionHasNoErrors();
    expect($incident->fresh()->status)->toBe('resolved');
    $this->actingAs($c['clientUser'])->get(route('dashboard'))->assertDontSee('PRIVATE-RESOLUTION')->assertInertia(fn (Assert $page) => $page->where('incidents.0.status', 'open')->missing('incidents.0.events'));
    expect(IncidentEvent::where('incident_id', $incident->id)->count())->toBe(3);
});

test('incident workflow enforces manager access optimistic versions and tenant boundaries', function () {
    $c = operationsContext();
    $incident = Incident::factory()->create(['concierge_id' => $c['tenant']->id, 'mission_id' => $c['mission']->id, 'author_id' => $c['worker']->id]);
    $payload = ['version' => 1, 'priority' => 'urgent', 'responsible_user_id' => $c['manager']->id, 'note' => 'Contrôle prioritaire.'];
    $this->actingAs($c['worker'])->post(route('incidents.follow-up', $incident->id), $payload)->assertForbidden();
    $this->actingAs($c['manager'])->post(route('incidents.follow-up', $incident->id), $payload)->assertRedirect()->assertSessionHasNoErrors();
    $this->post(route('incidents.follow-up', $incident->id), $payload)->assertConflict();
    $foreign = Incident::factory()->create();
    $this->post(route('incidents.follow-up', $foreign->id), $payload)->assertNotFound();
    $this->assertDatabaseCount('incident_events', 1);
});

test('a documented manual resolution can be reopened without losing history', function () {
    $c = operationsContext();
    $incident = Incident::factory()->create(['concierge_id' => $c['tenant']->id, 'mission_id' => $c['mission']->id, 'author_id' => $c['worker']->id]);
    $this->actingAs($c['manager'])->post(route('incidents.resolve', $incident->id), ['version' => 1, 'resolution_notes' => 'Le client a confirmé la remise en état.', 'resolution_photo_ids' => []])->assertRedirect()->assertSessionHasNoErrors();
    $this->post(route('incidents.reopen', $incident->id), ['version' => 2, 'note' => 'Le problème s’est reproduit.'])->assertRedirect()->assertSessionHasNoErrors();
    expect($incident->fresh()->status)->toBe('open')->and(IncidentEvent::where('incident_id', $incident->id)->count())->toBe(2);
});

test('PDF exports contain validated client content and exclude drafts access codes and internal incident notes', function () {
    $this->travelTo(new DateTimeImmutable('2026-10-12T08:00:00Z'));
    Storage::fake('local');
    $c = operationsContext();
    $report = Report::factory()->create(['concierge_id' => $c['tenant']->id, 'mission_id' => $c['mission']->id, 'author_id' => $c['worker']->id, 'status' => 'validated', 'notes' => 'Intervention réalisée. Les équipements et les accès visibles ont été contrôlés. Aucun problème nouveau à signaler.', 'reviewed_at' => now()]);
    $file = UploadedFile::fake()->image('photo.jpg', 800, 450);
    $path = $file->store('preuves', 'local');
    Photo::factory()->create(['concierge_id' => $c['tenant']->id, 'report_id' => $report->id, 'author_id' => $c['worker']->id, 'path' => $path, 'stage' => 'after']);
    Report::factory()->create(['concierge_id' => $c['tenant']->id, 'mission_id' => $c['mission']->id, 'author_id' => $c['worker']->id, 'notes' => 'SECRET-DRAFT']);
    Incident::factory()->create(['concierge_id' => $c['tenant']->id, 'mission_id' => $c['mission']->id, 'author_id' => $c['worker']->id, 'description' => 'SECRET-INCIDENT', 'resolution_notes' => 'SECRET-RESOLUTION', 'client_message' => 'Une observation a été prise en charge.', 'shared_at' => now(), 'shared_status' => 'open']);
    $pdf = $this->actingAs($c['clientUser'])->get(route('reports.pdf', $report->id))->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertSee('%PDF-1.4', false)->assertSee('/Subtype /Image', false)->assertDontSee('SECRET-ACCESS')->assertDontSee('SECRET-INSTRUCTIONS');
    $monthly = $this->get(route('clients.bilan', [$c['client']->id, '2026-10']))->assertOk()->assertDontSee('SECRET-DRAFT')->assertDontSee('SECRET-INCIDENT')->assertDontSee('SECRET-RESOLUTION');
    if (getenv('OPS_PDF_QA') === '1') {
        if (! is_dir(storage_path('app/qa'))) {
            mkdir(storage_path('app/qa'), 0755, true);
        }
        file_put_contents(storage_path('app/qa/exemple-compte-rendu.pdf'), $pdf->getContent());
        file_put_contents(storage_path('app/qa/exemple-bilan-mensuel.pdf'), $monthly->getContent());
    }
});

test('PDF routes refuse unpublished reports other clients and workers', function () {
    $c = operationsContext();
    $report = Report::factory()->create(['concierge_id' => $c['tenant']->id, 'mission_id' => $c['mission']->id, 'author_id' => $c['worker']->id, 'status' => 'submitted']);
    $this->actingAs($c['clientUser'])->get(route('reports.pdf', $report->id))->assertNotFound();
    $report->update(['status' => 'validated']);
    $this->actingAs($c['worker'])->get(route('reports.pdf', $report->id))->assertForbidden();
    $foreign = Client::factory()->create();
    $this->actingAs($c['clientUser'])->get(route('clients.bilan', [$foreign->id, '2026-10']))->assertNotFound();
    $this->get(route('clients.bilan', [$c['client']->id, '2026-99']))->assertUnprocessable();
});

test('scheduled mission reminders are idempotent and ignore accepted or distant missions', function () {
    $this->travelTo(new DateTimeImmutable('2026-10-12T08:00:00Z'));
    $c = operationsContext();
    $c['mission']->update(['status' => 'planned', 'scheduled_at' => now()->addHour()]);
    $this->artisan('app:remind-missions')->assertSuccessful();
    $this->artisan('app:remind-missions')->assertSuccessful();
    $this->assertDatabaseCount('business_notifications', 2);
    $c['mission']->update(['status' => 'accepted']);
    BusinessNotification::query()->delete();
    $this->artisan('app:remind-missions')->assertSuccessful();
    $this->assertDatabaseCount('business_notifications', 0);
});

test('sharing an incident cannot bypass its resolution workflow', function () {
    $c = operationsContext();
    $incident = Incident::factory()->create(['concierge_id' => $c['tenant']->id, 'mission_id' => $c['mission']->id, 'author_id' => $c['worker']->id]);
    $this->actingAs($c['manager'])->post(route('incidents.share', $incident->id), ['status' => 'resolved', 'client_message' => 'Information transmise.', 'shared_photo_ids' => []])->assertRedirect()->assertSessionHasNoErrors();
    expect($incident->fresh()->status)->toBe('open')->and($incident->fresh()->shared_status)->toBe('open');
});

test('notification polling returns only the scoped inbox and preserves the workspace component', function () {
    $c = operationsContext();
    app(NotifyActivity::class)->assigned($c['mission']);
    $this->actingAs($c['worker'])->get(route('dashboard'), ['X-Inertia' => 'true', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create(route('dashboard'))), 'X-Inertia-Partial-Component' => 'workspace/index', 'X-Inertia-Partial-Data' => 'notificationInbox'])->assertOk()->assertJsonPath('component', 'workspace/index')->assertJsonPath('props.notificationInbox.unread', 1)->assertJsonMissingPath('props.missions');
});
