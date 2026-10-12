<?php

use App\Actions\Concierge\SubscriptionManager;
use App\Models\ApplicationSetting;
use App\Models\Client;
use App\Models\Concierge;
use App\Models\Membership;
use App\Models\Message;
use App\Models\Mission;
use App\Models\PlanAddon;
use App\Models\Property;
use App\Models\ServiceTemplate;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Database\Seeders\SeoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);
function managementContext(): array
{
    $manager = User::factory()->create();
    $concierge = Concierge::factory()->create();
    Membership::factory()->create(['concierge_id' => $concierge->id, 'user_id' => $manager->id, 'role' => 'manager']);
    $client = Client::factory()->create(['concierge_id' => $concierge->id]);
    $property = Property::factory()->create(['concierge_id' => $concierge->id, 'client_id' => $client->id]);
    $template = ServiceTemplate::factory()->create(['concierge_id' => $concierge->id]);
    $subscription = app(SubscriptionManager::class)->ensure($concierge->id);

    return compact('manager', 'concierge', 'client', 'property', 'template', 'subscription');
}
function appAdmin(): User
{
    $user = User::factory()->create();
    $user->forceFill(['is_admin' => true])->save();

    return $user;
}
function planData(): array
{
    return ['name' => 'Équipe', 'monthly_price' => '49.90', 'limits' => ['staff' => 10, 'clients' => 50, 'properties' => 80, 'teams' => 4, 'missions_month' => 500, 'storage_mb' => 4096], 'features' => array_fill_keys(array_keys(SubscriptionManager::FEATURES), true), 'is_active' => true, 'is_public' => true];
}
function subscriptionData(array $context, array $extra = []): array
{
    return [...['plan_id' => $context['subscription']->subscription_plan_id, 'addon_ids' => [], 'status' => 'active', 'starts_on' => now()->toDateString(), 'expires_at' => now()->addMonth()->format('Y-m-d\TH:i'), 'limit_overrides' => [], 'feature_overrides' => []], ...$extra];
}
test('tenant managers receive 403 for every application administration write', function (string $route, array $payload) {
    $c = managementContext();
    $this->actingAs($c['manager'])->postJson(route($route), $payload)->assertForbidden();
    $this->assertDatabaseCount('administration_events', 0);
})->with([['admin.plans.store', []], ['admin.addons.store', []], ['admin.settings.update', []]]);
test('application administrators can create a priced plan with real quota settings', function () {
    $admin = appAdmin();
    $this->actingAs($admin)->post(route('admin.plans.store'), planData())->assertRedirect();
    $this->assertDatabaseHas('subscription_plans', ['name' => 'Équipe', 'monthly_price_cents' => 4990, 'is_public' => true]);
    $this->assertDatabaseHas('administration_events', ['user_id' => $admin->id, 'description' => 'Offre créée : Équipe']);
    $this->seed(SeoSeeder::class);
    $this->get(route('pricing'))->assertInertia(fn (Assert $page) => $page->has('plans', 1)->where('plans.0.monthly_price_cents', 4990));
});
test('catalog edits do not silently change existing subscription contracts', function () {
    $c = managementContext();
    $before = $c['subscription']->terms;
    $admin = appAdmin();
    $this->actingAs($admin)->post(route('admin.plans.update', $c['subscription']->subscription_plan_id), planData())->assertRedirect();
    expect($c['subscription']->fresh()->terms)->toBe($before);
    $this->post(route('admin.subscriptions.update', $c['concierge']->id), subscriptionData($c, ['refresh_terms' => true]))->assertRedirect();
    expect($c['subscription']->fresh()->terms['monthly_total_cents'])->toBe(4990);
    expect($c['subscription']->fresh()->terms['limits']['staff'])->toBe(10);
});
test('addon prices and capacities are combined and unlimited base limits stay unlimited', function () {
    $c = managementContext();
    $plan = SubscriptionPlan::factory()->create(['monthly_price_cents' => 2900, 'limits' => ['staff' => 4, 'clients' => null, 'properties' => 10, 'teams' => 1, 'missions_month' => 100, 'storage_mb' => 1024], 'features' => array_fill_keys(array_keys(SubscriptionManager::FEATURES), false)]);
    $addon = PlanAddon::factory()->create(['monthly_price_cents' => 900, 'limit_bonus' => ['staff' => 2, 'clients' => 30, 'properties' => 5], 'features' => ['offline' => true]]);
    $this->actingAs(appAdmin())->post(route('admin.subscriptions.update', $c['concierge']->id), subscriptionData($c, ['plan_id' => $plan->id, 'addon_ids' => [$addon->id]]))->assertRedirect();
    $terms = $c['subscription']->fresh()->terms;
    expect($terms['monthly_total_cents'])->toBe(3800);
    expect($terms['limits']['staff'])->toBe(6);
    expect($terms['limits']['properties'])->toBe(15);
    expect($terms['limits']['clients'])->toBeNull();
    expect($terms['features']['offline'])->toBeTrue();
});
test('individual subscription overrides affect price limits and features', function () {
    $c = managementContext();
    $this->actingAs(appAdmin())->post(route('admin.subscriptions.update', $c['concierge']->id), subscriptionData($c, ['limit_overrides' => ['clients' => 1], 'feature_overrides' => ['messaging' => false], 'price_override' => '39.50', 'notes' => 'PRIVATE-ADMIN-NOTE']))->assertRedirect();
    $this->actingAs($c['manager'])->postJson(route('clients.store'), ['name' => 'Second client', 'email' => 'second@example.test', 'kind' => 'individual'])->assertUnprocessable()->assertJsonValidationErrors('subscription');
    $this->postJson(route('messages.store'), ['client_id' => $c['client']->id, 'body' => 'Message'])->assertUnprocessable()->assertJsonValidationErrors('subscription');
    $this->get(route('subscription.show'))->assertInertia(fn (Assert $page) => $page->where('monthly_price_cents', 3950)->where('limits.clients', 1)->missing('subscription.notes'));
});
test('personnel quotas reject account creation without leaving an orphan user', function () {
    $c = managementContext();
    $c['subscription']->update(['limit_overrides' => ['staff' => 1]]);
    $users = User::count();
    $this->actingAs($c['manager'])->postJson(route('members.store'), ['name' => 'Intervenant', 'email' => 'worker@example.test', 'password' => 'LongPassword123!', 'password_confirmation' => 'LongPassword123!', 'role' => 'worker'])->assertUnprocessable()->assertJsonValidationErrors('subscription');
    $this->assertDatabaseCount('users', $users);
    $this->assertDatabaseCount('memberships', 1);
});
test('place quotas block creation when the limit is reached', function () {
    $c = managementContext();
    $c['subscription']->update(['limit_overrides' => ['properties' => 1]]);
    $this->actingAs($c['manager'])->postJson(route('properties.store'), ['client_id' => $c['client']->id, 'name' => 'Second place', 'address' => 'Address', 'kind' => 'residence'])->assertUnprocessable()->assertJsonValidationErrors('subscription');
    $this->assertDatabaseCount('properties', 1);
});
test('team quotas apply only when a new team would be created', function () {
    $c = managementContext();
    $c['subscription']->update(['limit_overrides' => ['teams' => 1]]);
    $this->actingAs($c['manager'])->postJson(route('members.store'), ['name' => 'Intervenant', 'email' => 'otherteam@example.test', 'password' => 'LongPassword123!', 'password_confirmation' => 'LongPassword123!', 'role' => 'worker', 'team' => 'Agence B'])->assertUnprocessable()->assertJsonValidationErrors('team');
    $this->assertDatabaseCount('users', 1);
});
test('monthly mission quotas include existing creations and preserve existing missions', function () {
    $c = managementContext();
    $c['subscription']->update(['limit_overrides' => ['missions_month' => 0]]);
    $this->actingAs($c['manager'])->postJson(route('missions.store'), ['property_id' => $c['property']->id, 'template_id' => $c['template']->id, 'title' => 'Mission', 'kind' => 'service', 'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'), 'duration' => 60])->assertUnprocessable()->assertJsonValidationErrors('subscription');
    $this->assertDatabaseCount('missions', 0);
});
test('photo storage quotas reject a report atomically and retain the old mission state', function () {
    Storage::fake('local');
    $c = managementContext();
    $c['subscription']->update(['limit_overrides' => ['storage_mb' => 0]]);
    $mission = Mission::factory()->create(['concierge_id' => $c['concierge']->id, 'property_id' => $c['property']->id, 'assigned_user_id' => $c['manager']->id, 'status' => 'accepted']);
    $this->actingAs($c['manager'])->postJson(route('missions.report', $mission->id), ['operation_id' => (string) Str::uuid(), 'version' => 1, 'notes' => 'Compte rendu', 'answers' => [true, true], 'photos' => [UploadedFile::fake()->image('proof.jpg')]])->assertUnprocessable()->assertJsonValidationErrors('photos');
    $this->assertDatabaseCount('reports', 0);
    $this->assertDatabaseCount('photos', 0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
    $this->assertDatabaseHas('missions', ['id' => $mission->id, 'status' => 'accepted', 'version' => 1]);
});
test('suspended or expired subscriptions receive 402 for business writes', function (string $status, bool $expired) {
    $c = managementContext();
    $c['subscription']->update(['status' => $status, 'expires_at' => $expired ? now()->subDay() : now()->addMonth()]);
    $this->actingAs($c['manager'])->postJson(route('clients.store'), ['name' => 'Blocked', 'email' => 'blocked@example.test', 'kind' => 'individual'])->assertStatus(402);
    $this->assertDatabaseMissing('clients', ['name' => 'Blocked']);
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->component('workspace/subscription')->where('blocked', true));
})->with([['suspended', false], ['active', true], ['cancelled', false]]);
test('the administrator can inspect and manage a tenant with an audited own identity', function () {
    $c = managementContext();
    $admin = appAdmin();
    $this->actingAs($admin)->post(route('admin.concierges.visit', $c['concierge']->id))->assertRedirect(route('dashboard'));
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('concierge.id', $c['concierge']->id));
    $this->assertDatabaseHas('administration_events', ['user_id' => $admin->id, 'concierge_id' => $c['concierge']->id]);
});
test('administrators cannot remove their own access', function () {
    $admin = appAdmin();
    $this->actingAs($admin)->postJson(route('admin.users.update', $admin->id), ['is_admin' => false, 'disabled' => true])->assertUnprocessable();
    expect($admin->fresh()->is_admin)->toBeTrue();
    expect($admin->fresh()->disabled_at)->toBeNull();
});
test('a suspended account loses access on its next request', function () {
    $c = managementContext();
    $c['manager']->forceFill(['disabled_at' => now()])->save();
    $this->actingAs($c['manager'])->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});
test('public registration cannot grant an application administrator role', function () {
    $this->post(route('register'), ['name' => 'New manager', 'email' => 'self@example.test', 'password' => 'LongPassword123!', 'password_confirmation' => 'LongPassword123!', 'is_admin' => true])->assertRedirect();
    $this->assertDatabaseHas('users', ['email' => 'self@example.test', 'is_admin' => false]);
});
test('closing registrations stops self signup without affecting existing users', function () {
    $c = managementContext();
    ApplicationSetting::query()->firstOrFail()->update(['registration_open' => false]);
    $this->post(route('register'), ['name' => 'New manager', 'email' => 'closed@example.test', 'password' => 'LongPassword123!', 'password_confirmation' => 'LongPassword123!'])->assertForbidden();
    $this->assertDatabaseMissing('users', ['email' => 'closed@example.test']);
    $this->actingAs($c['manager'])->get(route('dashboard'))->assertOk();
});

test('private pricing and administration notes are not exposed by the public pricing page', function () {
    $this->seed(SeoSeeder::class);
    $plan = SubscriptionPlan::factory()->create(['name' => 'Private commercial agreement', 'is_public' => false]);
    $this->get(route('pricing'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('plans', []));
});

test('disabling client portal blocks existing client access without revealing contract prices', function () {
    $c = managementContext();
    $clientUser = User::factory()->create();
    Membership::factory()->create(['concierge_id' => $c['concierge']->id, 'user_id' => $clientUser->id, 'role' => 'client', 'client_id' => $c['client']->id]);
    $c['subscription']->update(['feature_overrides' => ['client_portal' => false]]);
    $this->actingAs($clientUser)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('workspace/access-blocked')->missing('subscription'));
    $this->postJson(route('requests.store'), ['property_id' => $c['property']->id, 'title' => 'Request', 'description' => 'Details'])->assertForbidden();
    $this->assertDatabaseCount('intervention_requests', 0);
});
test('disabling messaging keeps recorded messages private and prevents new messages', function () {
    $c = managementContext();
    Message::factory()->create(['concierge_id' => $c['concierge']->id, 'client_id' => $c['client']->id, 'body' => 'Existing private conversation']);
    $c['subscription']->update(['feature_overrides' => ['messaging' => false]]);
    $this->actingAs($c['manager'])->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('messages', []));
    $this->postJson(route('messages.store'), ['client_id' => $c['client']->id, 'body' => 'New message'])->assertUnprocessable()->assertJsonValidationErrors('subscription');
    $this->assertDatabaseCount('messages', 1);
});
