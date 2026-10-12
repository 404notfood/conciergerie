<?php

use App\Models\Client;
use App\Models\Concierge;
use App\Models\Incident;
use App\Models\InterventionRequest;
use App\Models\Membership;
use App\Models\Message;
use App\Models\Mission;
use App\Models\Photo;
use App\Models\Property;
use App\Models\Report;
use App\Models\ServiceTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);
function conciergeContext(): array
{
    $concierge = Concierge::factory()->create();
    $manager = User::factory()->create();
    $worker = User::factory()->create();
    $clientUser = User::factory()->create();
    $client = Client::factory()->create(['concierge_id' => $concierge->id, 'email' => $clientUser->email]);
    foreach ([[$manager, 'manager', null], [$worker, 'worker', null], [$clientUser, 'client', $client->id]] as [$user,$role,$clientId]) {
        Membership::factory()->create(['concierge_id' => $concierge->id, 'user_id' => $user->id, 'role' => $role, 'client_id' => $clientId]);
    }
    $property = Property::factory()->create(['concierge_id' => $concierge->id, 'client_id' => $client->id, 'instructions' => 'PRIVATE-INSTRUCTION', 'access_code' => 'PRIVATE-CODE', 'offline_access' => true]);
    $template = ServiceTemplate::factory()->create(['concierge_id' => $concierge->id]);
    $mission = Mission::factory()->create(['concierge_id' => $concierge->id, 'property_id' => $property->id, 'assigned_user_id' => $worker->id, 'status' => 'accepted', 'checklist' => $template->checklist]);

    return compact('concierge', 'manager', 'worker', 'clientUser', 'client', 'property', 'template', 'mission');
}
function reportPayload(Mission $mission): array
{
    return ['operation_id' => (string) Str::uuid(), 'version' => $mission->version, 'notes' => 'Intervention réalisée, équipements contrôlés.', 'answers' => [true, true]];
}
test('a registered manager creates a private workspace with starter checklists', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('workspace.store'), ['name' => 'Ma conciergerie'])->assertRedirect(route('dashboard'));
    $this->assertDatabaseHas('concierges', ['name' => 'Ma conciergerie']);
    $this->assertDatabaseHas('memberships', ['user_id' => $user->id, 'role' => 'manager']);
    $this->assertDatabaseCount('service_templates', 3);
    $this->post(route('workspace.store'), ['name' => 'Second espace'])->assertConflict();
    $this->assertDatabaseCount('concierges', 1);
});
test('unauthenticated visitors cannot write business records', function () {
    $this->post(route('clients.store'), ['name' => 'Client'])->assertRedirect(route('login'));
    $this->assertDatabaseCount('clients', 0);
});
test('a manager saves a client and a property with an encrypted access code', function () {
    $c = conciergeContext();
    $this->actingAs($c['manager'])->post(route('clients.store'), ['name' => 'Entreprise cliente', 'email' => 'enterprise@example.test', 'kind' => 'company'])->assertRedirect();
    $client = Client::where('email', 'enterprise@example.test')->firstOrFail();
    $this->post(route('properties.store'), ['name' => 'Bureau', 'address' => 'Rue des bureaux', 'kind' => 'business', 'client_id' => $client->id, 'access_code' => '123456', 'offline_access' => true])->assertRedirect();
    $property = Property::where('name', 'Bureau')->firstOrFail();
    expect($property->access_code)->toBe('123456');
    expect($property->getRawOriginal('access_code'))->not->toContain('123456');
    $this->assertDatabaseHas('properties', ['client_id' => $client->id, 'concierge_id' => $c['concierge']->id]);
});
test('workers and clients receive 403 when managing the directory', function (string $role) {
    $c = conciergeContext();
    $this->actingAs($c[$role])->post(route('clients.store'), ['name' => 'Unauthorized', 'email' => 'no@example.test', 'kind' => 'individual'])->assertForbidden();
    $this->assertDatabaseMissing('clients', ['name' => 'Unauthorized']);
})->with(['worker', 'clientUser']);
test('foreign tenant references are rejected before a property is saved', function () {
    $c = conciergeContext();
    $foreign = Client::factory()->create();
    $this->actingAs($c['manager'])->postJson(route('properties.store'), ['name' => 'Foreign', 'address' => 'Address', 'kind' => 'residence', 'client_id' => $foreign->id])->assertUnprocessable()->assertJsonValidationErrors('client_id');
    $this->assertDatabaseMissing('properties', ['name' => 'Foreign']);
});
test('a copied checklist stays unchanged after its template is edited', function () {
    $c = conciergeContext();
    $this->actingAs($c['manager'])->post(route('missions.store'), ['property_id' => $c['property']->id, 'template_id' => $c['template']->id, 'assigned_user_id' => $c['worker']->id, 'title' => 'New mission', 'kind' => 'initial', 'scheduled_at' => '2026-10-13T09:00', 'duration' => 45])->assertRedirect();
    $c['template']->update(['checklist' => ['A different checklist']]);
    expect(Mission::where('title', 'New mission')->firstOrFail()->checklist)->toBe(['Vérifier les équipements', 'Prendre les preuves photo']);
});
test('a worker receives 404 for another tenant mission', function () {
    $c = conciergeContext();
    $foreign = Mission::factory()->create();
    $this->actingAs($c['worker'])->postJson(route('missions.report', $foreign->id), reportPayload($foreign))->assertNotFound();
    $this->assertDatabaseCount('reports', 0);
});
test('a worker receives 404 for a mission assigned to someone else', function () {
    $c = conciergeContext();
    $c['mission']->update(['assigned_user_id' => $c['manager']->id]);
    $this->actingAs($c['worker'])->postJson(route('missions.report', $c['mission']->id), reportPayload($c['mission']))->assertNotFound();
    $this->assertDatabaseCount('reports', 0);
});
test('a submitted report and photo stay private until explicit manager validation', function () {
    Storage::fake('local');
    $c = conciergeContext();
    $payload = [...reportPayload($c['mission']), 'photos' => [UploadedFile::fake()->image('proof.jpg')], 'stages' => ['before']];
    $this->actingAs($c['worker'])->postJson(route('missions.report', $c['mission']->id), $payload)->assertOk()->assertJsonPath('received', true);
    $report = Report::firstOrFail();
    $photo = Photo::firstOrFail();
    Storage::disk('local')->assertExists($photo->getRawOriginal('path'));
    $this->actingAs($c['clientUser'])->get(route('photos.show', $photo->id))->assertNotFound();
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('missions', []));
    $this->actingAs($c['manager'])->post(route('reports.review', $report->id), ['decision' => 'validated'])->assertRedirect();
    $this->assertDatabaseHas('reports', ['id' => $report->id, 'status' => 'validated', 'reviewer_id' => $c['manager']->id]);
    $this->assertDatabaseHas('activities', ['description' => 'Compte rendu validé et publié : '.$c['mission']->title]);
    $this->actingAs($c['clientUser'])->get(route('photos.show', $photo->id))->assertOk();
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->has('missions', 1)->where('missions.0.instructions', null)->where('properties.0.instructions', null)->where('missions.0.accessible_code', null));
});
test('repeating a received operation does not duplicate reports or files', function () {
    Storage::fake('local');
    $c = conciergeContext();
    $payload = [...reportPayload($c['mission']), 'photos' => [UploadedFile::fake()->image('proof.jpg')], 'stages' => ['after']];
    $this->actingAs($c['worker'])->postJson(route('missions.report', $c['mission']->id), $payload)->assertOk();
    $this->postJson(route('missions.report', $c['mission']->id), $payload)->assertOk();
    $this->assertDatabaseCount('reports', 1);
    $this->assertDatabaseCount('photos', 1);
    expect(Storage::disk('local')->allFiles())->toHaveCount(1);
});
test('an operation UUID cannot be reused for a different mission', function () {
    $c = conciergeContext();
    $payload = reportPayload($c['mission']);
    $this->actingAs($c['worker'])->postJson(route('missions.report', $c['mission']->id), $payload)->assertOk();
    $second = Mission::factory()->create(['property_id' => $c['property']->id, 'concierge_id' => $c['concierge']->id, 'assigned_user_id' => $c['worker']->id, 'status' => 'accepted']);
    $this->postJson(route('missions.report', $second->id), $payload)->assertConflict();
    $this->assertDatabaseCount('reports', 1);
});
test('an outdated draft receives 409 after reassignment and creates no report', function () {
    $c = conciergeContext();
    $payload = reportPayload($c['mission']);
    $this->actingAs($c['manager'])->post(route('missions.transition', $c['mission']->id), ['action' => 'reassign', 'version' => 1, 'assigned_user_id' => $c['worker']->id])->assertRedirect();
    $this->actingAs($c['worker'])->postJson(route('missions.report', $c['mission']->id), $payload)->assertConflict();
    $this->assertDatabaseCount('reports', 0);
    $this->assertDatabaseHas('missions', ['id' => $c['mission']->id, 'status' => 'planned', 'version' => 2]);
});
test('a cancelled mission rejects offline submission with 409', function () {
    $c = conciergeContext();
    $payload = reportPayload($c['mission']);
    $this->actingAs($c['manager'])->post(route('missions.transition', $c['mission']->id), ['action' => 'cancel', 'version' => 1])->assertRedirect();
    $this->actingAs($c['worker'])->postJson(route('missions.report', $c['mission']->id), $payload)->assertConflict();
    $this->assertDatabaseCount('reports', 0);
});
test('incomplete checklists receive 422 without creating reports', function () {
    $c = conciergeContext();
    $this->actingAs($c['worker'])->postJson(route('missions.report', $c['mission']->id), [...reportPayload($c['mission']), 'answers' => [true, false]])->assertUnprocessable()->assertJsonValidationErrors('answers');
    $this->assertDatabaseCount('reports', 0);
});
test('non image uploads receive 422 and are not stored', function () {
    Storage::fake('local');
    $c = conciergeContext();
    $this->actingAs($c['worker'])->postJson(route('missions.report', $c['mission']->id), [...reportPayload($c['mission']), 'photos' => [UploadedFile::fake()->create('script.php', 1, 'application/x-php')]])->assertUnprocessable()->assertJsonValidationErrors('photos.0');
    expect(Storage::disk('local')->allFiles())->toBe([]);
});
test('a requested correction retains the previous report version', function () {
    $c = conciergeContext();
    $this->actingAs($c['worker'])->postJson(route('missions.report', $c['mission']->id), reportPayload($c['mission']))->assertOk();
    $report = Report::firstOrFail();
    $this->actingAs($c['manager'])->post(route('reports.review', $report->id), ['decision' => 'changes_requested', 'feedback' => 'Merci de compléter les observations.'])->assertRedirect();
    $c['mission']->refresh();
    $this->actingAs($c['worker'])->postJson(route('missions.report', $c['mission']->id), reportPayload($c['mission']))->assertOk();
    $this->assertDatabaseCount('reports', 2);
    $this->assertDatabaseHas('reports', ['id' => $report->id, 'status' => 'changes_requested', 'feedback' => 'Merci de compléter les observations.']);
});
test('workers cannot publish a report and clients cannot self validate', function (string $role) {
    $c = conciergeContext();
    $report = Report::factory()->create(['concierge_id' => $c['concierge']->id, 'mission_id' => $c['mission']->id, 'author_id' => $c['worker']->id]);
    $this->actingAs($c[$role])->post(route('reports.review', $report->id), ['decision' => 'validated'])->assertForbidden();
    $this->assertDatabaseHas('reports', ['id' => $report->id, 'status' => 'submitted']);
})->with(['worker', 'clientUser']);
test('a manager can validate a report they authored with a recorded identity', function () {
    $c = conciergeContext();
    $c['mission']->update(['assigned_user_id' => $c['manager']->id]);
    $this->actingAs($c['manager'])->postJson(route('missions.report', $c['mission']->id), reportPayload($c['mission']))->assertOk();
    $report = Report::firstOrFail();
    $this->post(route('reports.review', $report->id), ['decision' => 'validated'])->assertRedirect();
    $this->assertDatabaseHas('reports', ['id' => $report->id, 'author_id' => $c['manager']->id, 'reviewer_id' => $c['manager']->id, 'status' => 'validated']);
});
test('incidents are received before report validation and remain private until selected sharing', function () {
    Storage::fake('local');
    $c = conciergeContext();
    $this->actingAs($c['worker'])->postJson(route('missions.incident', $c['mission']->id), ['operation_id' => (string) Str::uuid(), 'version' => 1, 'description' => 'PRIVATE-INCIDENT'])->assertOk();
    $incident = Incident::firstOrFail();
    $report = Report::factory()->create(['concierge_id' => $c['concierge']->id, 'mission_id' => $c['mission']->id, 'author_id' => $c['worker']->id]);
    $first = Photo::factory()->create(['concierge_id' => $c['concierge']->id, 'report_id' => $report->id, 'path' => 'selected.jpg']);
    $second = Photo::factory()->create(['concierge_id' => $c['concierge']->id, 'report_id' => $report->id, 'path' => 'private.jpg']);
    Storage::disk('local')->put('selected.jpg', 'image');
    Storage::disk('local')->put('private.jpg', 'image');
    $this->actingAs($c['clientUser'])->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('incidents', []));
    $this->actingAs($c['manager'])->post(route('incidents.share', $incident->id), ['client_message' => 'Une réparation est nécessaire.', 'shared_photo_ids' => [$first->id], 'status' => 'open'])->assertRedirect();
    $this->actingAs($c['clientUser'])->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->has('incidents', 1)->where('incidents.0.description', 'Une réparation est nécessaire.')->missing('incidents.0.mission.instructions')->missing('incidents.0.mission.property.instructions'));
    $this->get(route('photos.show', $first->id))->assertOk();
    $this->get(route('photos.show', $second->id))->assertNotFound();
});
test('sharing an incident refuses photos from a different mission with 404', function () {
    $c = conciergeContext();
    $incident = Incident::factory()->create(['concierge_id' => $c['concierge']->id, 'mission_id' => $c['mission']->id]);
    $photo = Photo::factory()->create();
    $this->actingAs($c['manager'])->post(route('incidents.share', $incident->id), ['client_message' => 'Explanation', 'shared_photo_ids' => [$photo->id], 'status' => 'open'])->assertNotFound();
    $this->assertDatabaseHas('incidents', ['id' => $incident->id, 'shared_at' => null]);
});
test('client requests create a mission only after explicit manager approval', function () {
    $c = conciergeContext();
    $this->actingAs($c['clientUser'])->post(route('requests.store'), ['property_id' => $c['property']->id, 'title' => 'Réparation', 'description' => 'Vérifier la fenêtre.'])->assertRedirect();
    $this->assertDatabaseCount('missions', 1);
    $request = InterventionRequest::firstOrFail();
    $decision = ['decision' => 'accepted', 'feedback' => 'Nous prévoyons une visite.', 'scheduled_at' => '2026-10-13T10:00', 'template_id' => $c['template']->id, 'assigned_user_id' => $c['worker']->id];
    $this->actingAs($c['manager'])->post(route('requests.decide', $request->id), $decision)->assertRedirect();
    $this->assertDatabaseCount('missions', 2);
    $this->assertDatabaseHas('intervention_requests', ['id' => $request->id, 'status' => 'accepted']);
    $this->post(route('requests.decide', $request->id), $decision)->assertConflict();
    $this->assertDatabaseCount('missions', 2);
});
test('a client cannot message another client in the same concierge', function () {
    $c = conciergeContext();
    $other = Client::factory()->create(['concierge_id' => $c['concierge']->id]);
    $this->actingAs($c['clientUser'])->post(route('messages.store'), ['client_id' => $other->id, 'body' => 'Wrong audience'])->assertNotFound();
    $this->assertDatabaseCount('messages', 0);
});
test('messages persist for their client and are invisible to other clients', function () {
    $c = conciergeContext();
    $other = Client::factory()->create(['concierge_id' => $c['concierge']->id]);
    Message::factory()->create(['concierge_id' => $c['concierge']->id, 'client_id' => $other->id, 'body' => 'PRIVATE-MESSAGE']);
    $this->actingAs($c['clientUser'])->post(route('messages.store'), ['client_id' => $c['client']->id, 'body' => 'Bonjour'])->assertRedirect();
    $this->assertDatabaseHas('messages', ['client_id' => $c['client']->id, 'body' => 'Bonjour', 'author_id' => $c['clientUser']->id]);
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->has('messages', 1)->where('messages.0.body', 'Bonjour'));
});
test('terrain preparation returns only accepted own missions and removes forbidden codes', function () {
    $c = conciergeContext();
    Mission::factory()->create(['concierge_id' => $c['concierge']->id, 'property_id' => $c['property']->id, 'assigned_user_id' => $c['manager']->id, 'status' => 'accepted']);
    $this->actingAs($c['worker'])->getJson(route('terrain.data'))->assertOk()->assertJsonCount(1, 'missions')->assertJsonPath('missions.0.property.access_code', 'PRIVATE-CODE');
    $c['property']->update(['offline_access' => false]);
    $this->getJson(route('terrain.data'))->assertOk()->assertJsonPath('missions.0.property.access_code', null);
    $c['mission']->update(['status' => 'cancelled']);
    $this->getJson(route('terrain.data'))->assertOk()->assertJsonCount(0, 'missions');
});
test('a client receives 403 for terrain preparation', function () {
    $c = conciergeContext();
    $this->actingAs($c['clientUser'])->getJson(route('terrain.data'))->assertForbidden();
});
test('iCal imports persist and deduplicate UID within a source and place', function () {
    $c = conciergeContext();
    $calendar = "BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:booking-1\r\nDTSTART;VALUE=DATE:20261013\r\nDTEND;VALUE=DATE:20261017\r\nSUMMARY:Période importée\r\nEND:VEVENT\r\nEND:VCALENDAR";
    $payload = ['property_id' => $c['property']->id, 'source' => 'airbnb', 'calendar' => UploadedFile::fake()->createWithContent('calendar.ics', $calendar)];
    $this->actingAs($c['manager'])->post(route('stays.import'), $payload)->assertRedirect();
    $this->post(route('stays.import'), $payload)->assertRedirect();
    $this->assertDatabaseCount('stays', 1);
    $this->assertDatabaseHas('stays', ['kind' => 'unclassified', 'starts_on' => '2026-10-13', 'ends_on' => '2026-10-17', 'external_uid' => 'booking-1']);
    $this->assertDatabaseCount('missions', 1);
});
test('invalid iCal files fail atomically without partial stays', function () {
    $c = conciergeContext();
    $calendar = "BEGIN:VCALENDAR\nBEGIN:VEVENT\nUID:valid\nDTSTART:20261013\nDTEND:20261017\nEND:VEVENT\nBEGIN:VEVENT\nUID:bad\nDTSTART:INVALID\nDTEND:20261017\nEND:VEVENT\nEND:VCALENDAR";
    $this->actingAs($c['manager'])->post(route('stays.import'), ['property_id' => $c['property']->id, 'source' => 'ical', 'calendar' => UploadedFile::fake()->createWithContent('calendar.ics', $calendar)])->assertSessionHasErrors('calendar');
    $this->assertDatabaseCount('stays', 0);
});

test('an enterprise coordinator designates only a person from their own company', function () {
    $c = conciergeContext();
    $coordinator = User::factory()->create();
    Membership::factory()->create(['concierge_id' => $c['concierge']->id, 'user_id' => $coordinator->id, 'role' => 'coordinator', 'company' => 'Entreprise A']);
    Membership::where('user_id', $c['worker']->id)->update(['company' => 'Entreprise A']);
    $c['mission']->update(['status' => 'planned', 'assigned_user_id' => null, 'assigned_company' => 'Entreprise A']);
    $this->actingAs($coordinator)->post(route('missions.designate', $c['mission']->id), ['version' => 1, 'assigned_user_id' => $c['worker']->id])->assertRedirect();
    $this->assertDatabaseHas('missions', ['id' => $c['mission']->id, 'assigned_user_id' => $c['worker']->id, 'status' => 'planned', 'version' => 2]);
    $this->actingAs($c['worker'])->post(route('missions.transition', $c['mission']->id), ['action' => 'accept', 'version' => 2])->assertRedirect();
    $this->assertDatabaseHas('missions', ['id' => $c['mission']->id, 'status' => 'accepted']);
});
test('a coordinator cannot designate another company worker or inspect client conversations', function () {
    $c = conciergeContext();
    $coordinator = User::factory()->create();
    Membership::factory()->create(['concierge_id' => $c['concierge']->id, 'user_id' => $coordinator->id, 'role' => 'coordinator', 'company' => 'Entreprise A']);
    $c['mission']->update(['status' => 'planned', 'assigned_user_id' => null, 'assigned_company' => 'Entreprise A']);
    Message::factory()->create(['concierge_id' => $c['concierge']->id, 'client_id' => $c['client']->id, 'body' => 'PRIVATE-MESSAGE']);
    $this->actingAs($coordinator)->postJson(route('missions.designate', $c['mission']->id), ['version' => 1, 'assigned_user_id' => $c['worker']->id])->assertUnprocessable()->assertJsonValidationErrors('assigned_user_id');
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->has('missions', 1)->where('messages', [])->where('requests', [])->where('clients', []));
});
