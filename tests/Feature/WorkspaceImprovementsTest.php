<?php

use App\Actions\Concierge\SubscriptionManager;
use App\Models\Client;
use App\Models\Concierge;
use App\Models\Incident;
use App\Models\Membership;
use App\Models\Mission;
use App\Models\MissionRecurrence;
use App\Models\Property;
use App\Models\ServiceTemplate;
use App\Models\StaffAvailability;
use App\Models\Stay;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function improvementContext(): array
{
    $tenant = Concierge::factory()->create();
    $manager = User::factory()->create();
    $worker = User::factory()->create();
    Membership::factory()->create(['concierge_id' => $tenant->id, 'user_id' => $manager->id, 'role' => 'manager']);
    Membership::factory()->create(['concierge_id' => $tenant->id, 'user_id' => $worker->id, 'role' => 'worker']);
    $client = Client::factory()->create(['concierge_id' => $tenant->id]);
    $property = Property::factory()->create(['concierge_id' => $tenant->id, 'client_id' => $client->id, 'access_code' => 'SECRET-123']);
    $template = ServiceTemplate::factory()->create(['concierge_id' => $tenant->id]);
    app(SubscriptionManager::class)->ensure($tenant->id);

    return compact('tenant', 'manager', 'worker', 'client', 'property', 'template');
}

function improvementMission(array $c, array $overrides = []): Mission
{
    return Mission::factory()->create([...['concierge_id' => $c['tenant']->id, 'property_id' => $c['property']->id, 'assigned_user_id' => $c['worker']->id, 'scheduled_at' => '2026-10-15 08:00:00', 'duration' => 60, 'status' => 'accepted'], ...$overrides]);
}

function reschedulePayload(Mission $mission, array $overrides = []): array
{
    return [...['version' => $mission->version, 'scheduled_at' => '2026-10-16T10:00', 'duration' => 90, 'assigned_user_id' => $mission->assigned_user_id], ...$overrides];
}

test('managers update directory resources while preserving ownership and mission snapshots', function (string $resource) {
    $c = improvementContext();
    $mission = improvementMission($c, ['checklist' => ['Checklist historique']]);
    $stay = Stay::factory()->create(['concierge_id' => $c['tenant']->id, 'property_id' => $c['property']->id]);
    [$record, $payload, $expected] = match ($resource) {
        'clients' => [$c['client'], ['name' => 'Client corrigé', 'email' => $c['client']->email, 'phone' => '0600000000', 'kind' => 'company'], ['name' => 'Client corrigé']],
        'properties' => [$c['property'], ['name' => 'Lieu corrigé', 'address' => '20 rue du Port', 'kind' => 'residence', 'instructions' => 'Nouvelle consigne', 'access_code' => '', 'offline_access' => false, 'client_id' => 99999], ['name' => 'Lieu corrigé', 'client_id' => $c['client']->id]],
        'templates' => [$c['template'], ['name' => 'Prestation corrigée', 'checklist' => ['Nouvelle tâche']], ['name' => 'Prestation corrigée']],
        'stays' => [$stay, ['title' => 'Séjour corrigé', 'starts_on' => '2026-10-20', 'ends_on' => '2026-10-23', 'kind' => 'stay', 'property_id' => 99999], ['title' => 'Séjour corrigé', 'property_id' => $c['property']->id]],
    };

    $this->actingAs($c['manager'])->post(route('directory.update', [$resource, $record->id]), [...$payload, 'concierge_id' => 99999, 'archived_at' => '2026-01-01'])->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseHas($record->getTable(), [...$expected, 'id' => $record->id, 'concierge_id' => $c['tenant']->id, 'archived_at' => null]);
    expect($mission->fresh()->checklist)->toBe(['Checklist historique']);
    expect($c['property']->fresh()->access_code)->toBe('SECRET-123');
    $this->assertDatabaseHas('activities', ['user_id' => $c['manager']->id, 'description' => 'Fiche modifiée : '.($expected['name'] ?? $expected['title'])]);
})->with(['clients', 'properties', 'templates', 'stays']);

test('access codes can be replaced or explicitly removed without being exposed', function () {
    $c = improvementContext();
    $payload = ['name' => $c['property']->name, 'address' => $c['property']->address, 'kind' => 'residence', 'offline_access' => false, 'access_code' => 'NEW-456'];
    $this->actingAs($c['manager'])->post(route('directory.update', ['properties', $c['property']->id]), $payload)->assertSessionHasNoErrors();
    expect($c['property']->fresh()->access_code)->toBe('NEW-456');
    expect($c['property']->fresh()->toArray())->not->toHaveKey('access_code');
    $this->post(route('directory.update', ['properties', $c['property']->id]), [...$payload, 'clear_access_code' => true])->assertSessionHasNoErrors();
    expect($c['property']->fresh()->access_code)->toBeNull();
});

test('updates reject duplicate client emails and invalid stay periods', function () {
    $c = improvementContext();
    $other = Client::factory()->create(['concierge_id' => $c['tenant']->id]);
    $stay = Stay::factory()->create(['concierge_id' => $c['tenant']->id, 'property_id' => $c['property']->id]);
    $this->actingAs($c['manager'])->post(route('directory.update', ['clients', $c['client']->id]), ['name' => 'Duplicate', 'email' => $other->email, 'kind' => 'individual'])->assertSessionHasErrors('email');
    $this->post(route('directory.update', ['stays', $stay->id]), ['title' => 'Invalid', 'starts_on' => '2026-10-22', 'ends_on' => '2026-10-21', 'kind' => 'stay'])->assertSessionHasErrors('ends_on');
    expect($c['client']->fresh()->email)->toBe($c['client']->email);
    expect($stay->fresh()->title)->toBe($stay->title);
});

test('archiving and restoring preserves linked mission history', function () {
    $c = improvementContext();
    $mission = improvementMission($c);
    $url = route('directory.archive', ['properties', $c['property']->id]);
    $this->actingAs($c['manager'])->post($url, ['archived' => true])->assertRedirect()->assertSessionHasNoErrors();
    expect($c['property']->fresh()->archived_at)->not->toBeNull();
    $this->assertModelExists($mission);
    expect($mission->fresh()->property->name)->toBe($c['property']->name);
    $this->post($url, ['archived' => false])->assertRedirect()->assertSessionHasNoErrors();
    expect($c['property']->fresh()->archived_at)->toBeNull();
});

test('client archival requires archived properties and property restoration requires an active client', function () {
    $c = improvementContext();
    $this->actingAs($c['manager'])->post(route('directory.archive', ['clients', $c['client']->id]), ['archived' => true])->assertSessionHasErrors(['archive' => 'Archivez d’abord les lieux de ce client.']);
    $this->post(route('directory.archive', ['properties', $c['property']->id]), ['archived' => true])->assertSessionHasNoErrors();
    $this->post(route('directory.archive', ['clients', $c['client']->id]), ['archived' => true])->assertSessionHasNoErrors();
    $this->post(route('directory.archive', ['properties', $c['property']->id]), ['archived' => false])->assertSessionHasErrors(['archive' => 'Restaurez d’abord le client de ce lieu.']);
    expect($c['property']->fresh()->archived_at)->not->toBeNull();
});

test('recurrences must be suspended before archival and cannot resume with archived resources', function (string $resource) {
    $c = improvementContext();
    $rule = MissionRecurrence::factory()->create(['concierge_id' => $c['tenant']->id, 'property_id' => $c['property']->id, 'template_id' => $c['template']->id]);
    $record = $resource === 'properties' ? $c['property'] : $c['template'];
    $this->actingAs($c['manager'])->post(route('directory.archive', [$resource, $record->id]), ['archived' => true])->assertSessionHasErrors('archive');
    $rule->update(['is_active' => false]);
    $this->post(route('directory.archive', [$resource, $record->id]), ['archived' => true])->assertSessionHasNoErrors();
    $this->post(route('recurrences.toggle', $rule->id), ['is_active' => true])->assertConflict();
    expect($rule->fresh()->is_active)->toBeFalse();
})->with(['properties', 'templates']);

test('new missions reject archived properties and templates', function (string $resource) {
    $c = improvementContext();
    ($resource === 'property_id' ? $c['property'] : $c['template'])->forceFill(['archived_at' => now()])->save();
    $this->actingAs($c['manager'])->post(route('missions.store'), ['property_id' => $c['property']->id, 'template_id' => $c['template']->id, 'title' => 'Mission', 'kind' => 'service', 'scheduled_at' => '2026-10-16T10:00', 'duration' => 60])->assertSessionHasErrors($resource);
    $this->assertDatabaseCount('missions', 0);
})->with(['property_id', 'template_id']);

test('directory mutations enforce authentication manager access and tenant isolation', function (string $action) {
    $c = improvementContext();
    $foreign = Client::factory()->create();
    $this->post(route('directory.'.$action, ['clients', $c['client']->id]), ['archived' => true])->assertRedirect(route('login'));
    $this->actingAs($c['worker'])->post(route('directory.'.$action, ['clients', $c['client']->id]), ['archived' => true])->assertForbidden();
    $this->actingAs($c['manager'])->post(route('directory.'.$action, ['clients', $foreign->id]), ['archived' => true])->assertNotFound();
    expect($foreign->fresh()->archived_at)->toBeNull();
    $this->assertDatabaseCount('activities', 0);
})->with(['update', 'archive']);

test('replanning stores Paris time in UTC resets acceptance and notifies affected workers', function () {
    $c = improvementContext();
    $mission = improvementMission($c);
    $other = User::factory()->create();
    Membership::factory()->create(['concierge_id' => $c['tenant']->id, 'user_id' => $other->id, 'role' => 'worker']);
    $this->actingAs($c['manager'])->post(route('missions.reschedule', $mission->id), reschedulePayload($mission, ['assigned_user_id' => $other->id]))->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseHas('missions', ['id' => $mission->id, 'scheduled_at' => '2026-10-16 08:00:00', 'duration' => 90, 'assigned_user_id' => $other->id, 'status' => 'planned', 'version' => $mission->version + 1]);
    $this->assertDatabaseHas('business_notifications', ['user_id' => $other->id, 'event' => 'mission_assigned']);
    $this->assertDatabaseHas('business_notifications', ['user_id' => $c['worker']->id, 'event' => 'mission_response']);
    $this->assertDatabaseHas('activities', ['mission_id' => $mission->id, 'description' => 'Mission replanifiée : '.$mission->title]);
});

test('replanning rejects unavailable workers and overlapping missions without side effects', function (string $conflict) {
    $c = improvementContext();
    $mission = improvementMission($c);
    if ($conflict === 'absence') {
        StaffAvailability::factory()->create(['concierge_id' => $c['tenant']->id, 'user_id' => $c['worker']->id, 'starts_at' => '2026-10-16 08:00:00', 'ends_at' => '2026-10-16 09:00:00']);
    } else {
        improvementMission($c, ['scheduled_at' => '2026-10-16 08:30:00']);
    }
    $this->actingAs($c['manager'])->post(route('missions.reschedule', $mission->id), reschedulePayload($mission))->assertSessionHasErrors('assigned_user_id');
    expect($mission->fresh()->version)->toBe($mission->version);
    expect($mission->fresh()->status)->toBe('accepted');
    $this->assertDatabaseCount('business_notifications', 0);
    $this->assertDatabaseCount('activities', 0);
})->with(['absence', 'overlap']);

test('replanning protects mission versions started work and tenant boundaries', function () {
    $c = improvementContext();
    $mission = improvementMission($c);
    $url = route('missions.reschedule', $mission->id);
    $this->post($url, reschedulePayload($mission))->assertRedirect(route('login'));
    $this->actingAs($c['worker'])->post($url, reschedulePayload($mission))->assertForbidden();
    $this->actingAs($c['manager'])->post($url, reschedulePayload($mission, ['version' => 0]))->assertConflict();
    $foreign = Mission::factory()->create();
    $this->post(route('missions.reschedule', $foreign->id), reschedulePayload($foreign, ['assigned_user_id' => $c['worker']->id]))->assertNotFound();
    $mission->update(['status' => 'in_progress']);
    $this->post($url, reschedulePayload($mission))->assertSessionHasErrors('mission');
    expect($mission->fresh()->version)->toBe($mission->version);
    $this->assertDatabaseCount('business_notifications', 0);
});

test('the action center distinguishes urgent incidents late starts assignment and validation', function () {
    $this->travelTo(new DateTimeImmutable('2026-10-15T10:00:00Z'));
    $c = improvementContext();
    $late = improvementMission($c);
    $unassigned = improvementMission($c, ['assigned_user_id' => null, 'scheduled_at' => '2026-10-16 08:00:00', 'status' => 'planned']);
    $pending = improvementMission($c, ['scheduled_at' => '2026-10-16 09:00:00', 'status' => 'planned']);
    $submitted = improvementMission($c, ['status' => 'submitted']);
    foreach (['validated', 'cancelled', 'in_progress'] as $status) {
        improvementMission($c, ['status' => $status]);
    }
    improvementMission($c, ['assigned_user_id' => null, 'scheduled_at' => '2026-10-20 08:00:00', 'status' => 'planned']);
    $incident = Incident::factory()->create(['concierge_id' => $c['tenant']->id, 'mission_id' => $late->id, 'author_id' => $c['worker']->id, 'priority' => 'urgent']);
    Incident::factory()->create(['concierge_id' => $c['tenant']->id, 'mission_id' => $late->id, 'author_id' => $c['worker']->id, 'priority' => 'urgent', 'status' => 'resolved']);
    improvementMission(improvementContext(), ['status' => 'submitted']);

    $this->actingAs($c['manager'])->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('actionCenter.urgent_incidents', [$incident->id])->where('actionCenter.late_missions', [$late->id])->where('actionCenter.unassigned_missions', [$unassigned->id])->where('actionCenter.pending_missions', [$pending->id])->where('actionCenter.reports', [$submitted->id]));
    $this->actingAs($c['worker'])->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('actionCenter', null));
});
