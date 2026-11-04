<?php

use App\Actions\Concierge\SubscriptionManager;
use App\Models\Concierge;
use App\Models\ConciergeSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

function manualConciergeData(int $planId, array $overrides = []): array
{
    return [...[
        'name' => 'Conciergerie de présentation',
        'manager_name' => 'Camille Démo',
        'email' => 'presentation@example.test',
        'password' => 'PresentationPassword123!',
        'password_confirmation' => 'PresentationPassword123!',
        'plan_id' => $planId,
        'is_demo' => false,
    ], ...$overrides];
}

test('administrators can create a concierge and its manager even when registrations are closed', function () {
    $this->travelTo(now()->setDate(2026, 11, 4)->startOfDay());
    $admin = User::factory()->create(['is_admin' => true]);
    app(SubscriptionManager::class)->settings()->update(['registration_open' => false, 'trial_days' => 21]);
    $plan = SubscriptionPlan::factory()->create(['monthly_price_cents' => 4900]);

    $this->actingAs($admin)->post(route('admin.concierges.store'), manualConciergeData($plan->id, ['is_admin' => true, 'role' => 'worker']))
        ->assertRedirect(route('admin.index', ['view' => 'concierges']))
        ->assertSessionHas('success');

    $concierge = Concierge::where('name', 'Conciergerie de présentation')->firstOrFail();
    $manager = User::where('email', 'presentation@example.test')->firstOrFail();
    $this->assertDatabaseHas('memberships', ['concierge_id' => $concierge->id, 'user_id' => $manager->id, 'role' => 'manager']);
    $this->assertDatabaseHas('users', ['id' => $manager->id, 'name' => 'Camille Démo', 'is_admin' => false, 'disabled_at' => null]);
    expect($manager->hasVerifiedEmail())->toBeTrue();
    expect(Hash::check('PresentationPassword123!', $manager->password))->toBeTrue();
    $subscription = ConciergeSubscription::where('concierge_id', $concierge->id)->firstOrFail();
    expect($subscription->status)->toBe('trialing');
    expect($subscription->subscription_plan_id)->toBe($plan->id);
    expect($subscription->terms)->toBe(app(SubscriptionManager::class)->terms($plan, []));
    expect($subscription->starts_on)->toBe('2026-11-04');
    expect($subscription->expires_at->toDateString())->toBe('2026-11-25');
    expect($subscription->price_override_cents)->toBeNull();
    $this->assertDatabaseHas('administration_events', ['user_id' => $admin->id, 'concierge_id' => $concierge->id, 'description' => 'Conciergerie créée : Conciergerie de présentation']);
    expect(DB::table('administration_events')->first()->details)->not->toContain('PresentationPassword123!');
    $this->get(route('admin.index', ['view' => 'concierges']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('administration/index')
        ->where('concierges.0.id', $concierge->id)
        ->where('concierges.0.members.0.user.email', 'presentation@example.test')
        ->missing('concierges.0.members.0.user.password'));
});

test('a manually created demo is free without expiration and its manager can log in immediately', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $plan = SubscriptionPlan::factory()->create(['monthly_price_cents' => 9900]);

    $this->actingAs($admin)->post(route('admin.concierges.store'), manualConciergeData($plan->id, ['is_demo' => true]))->assertRedirect();

    $concierge = Concierge::where('name', 'Conciergerie de présentation')->firstOrFail();
    $subscription = ConciergeSubscription::where('concierge_id', $concierge->id)->firstOrFail();
    expect($subscription->status)->toBe('active');
    expect($subscription->expires_at)->toBeNull();
    expect($subscription->price_override_cents)->toBe(0);
    expect($subscription->terms['monthly_total_cents'])->toBe(9900);
    expect($subscription->notes)->toBe('Compte de démonstration créé manuellement.');
    expect(app(SubscriptionManager::class)->limits($subscription))->toBe($plan->limits);
    expect(app(SubscriptionManager::class)->features($subscription))->toBe($plan->features);
    $this->post(route('logout'));
    $this->post(route('login'), ['email' => 'presentation@example.test', 'password' => 'PresentationPassword123!'])->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs(User::where('email', 'presentation@example.test')->firstOrFail());
    $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('workspace/index')
        ->where('concierge.id', $concierge->id)
        ->where('membership.role', 'manager')
        ->where('subscriptionSummary.monthly_price_cents', 0));
});

test('guests cannot create concierges from administration', function () {
    $this->post(route('admin.concierges.store'), [])->assertRedirect(route('login'));

    $this->assertDatabaseCount('concierges', 0);
    $this->assertDatabaseCount('administration_events', 0);
});

test('non administrators cannot create concierges from administration', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('admin.concierges.store'), [])->assertForbidden();

    $this->assertDatabaseCount('concierges', 0);
    $this->assertDatabaseCount('administration_events', 0);
});

test('invalid manual concierge details create no partial account', function (array $overrides, array $errors) {
    $admin = User::factory()->create(['is_admin' => true]);
    $plan = SubscriptionPlan::factory()->create();

    $this->actingAs($admin)->postJson(route('admin.concierges.store'), manualConciergeData($plan->id, $overrides))
        ->assertUnprocessable()->assertJsonValidationErrors($errors);

    $this->assertDatabaseCount('concierges', 0);
    $this->assertDatabaseCount('memberships', 0);
    $this->assertDatabaseCount('concierge_subscriptions', 0);
    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('administration_events', 0);
})->with([
    'missing identity' => [['name' => '', 'manager_name' => '', 'email' => ''], ['name', 'manager_name', 'email']],
    'invalid email' => [['email' => 'invalid'], ['email']],
    'short password' => [['password' => 'short', 'password_confirmation' => 'short'], ['password']],
    'different confirmation' => [['password_confirmation' => 'DifferentPassword123!'], ['password']],
    'unknown plan' => [['plan_id' => 999999], ['plan_id']],
    'invalid demo option' => [['is_demo' => 'invalid'], ['is_demo']],
]);

test('manual creation rejects archived plans', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $plan = SubscriptionPlan::factory()->create(['is_active' => false]);

    $this->actingAs($admin)->postJson(route('admin.concierges.store'), manualConciergeData($plan->id))
        ->assertUnprocessable()->assertJsonValidationErrors('plan_id');

    $this->assertDatabaseCount('concierges', 0);
    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('administration_events', 0);
});

test('manual creation refuses an existing email without changing the existing account', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $existing = User::factory()->create(['email' => 'presentation@example.test']);
    $original = $existing->fresh()->getAttributes();
    $plan = SubscriptionPlan::factory()->create();

    $this->actingAs($admin)->postJson(route('admin.concierges.store'), manualConciergeData($plan->id))
        ->assertUnprocessable()->assertJsonValidationErrors('email');

    expect($existing->fresh()->getAttributes())->toBe($original);
    $this->assertDatabaseCount('users', 2);
    $this->assertDatabaseCount('concierges', 0);
    $this->assertDatabaseCount('memberships', 0);
    $this->assertDatabaseCount('administration_events', 0);
});
