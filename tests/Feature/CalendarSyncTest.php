<?php

use App\Actions\Concierge\FetchCalendar;
use App\Actions\Concierge\ImportCalendar;
use App\Actions\Concierge\SubscriptionManager;
use App\Models\CalendarFeed;
use App\Models\Client;
use App\Models\Concierge;
use App\Models\Membership;
use App\Models\Mission;
use App\Models\Property;
use App\Models\Stay;
use App\Models\User;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

function calendarContext(): array
{
    $tenant = Concierge::factory()->create();
    $manager = User::factory()->create();
    Membership::factory()->create(['concierge_id' => $tenant->id, 'user_id' => $manager->id, 'role' => 'manager']);
    $client = Client::factory()->create(['concierge_id' => $tenant->id]);
    $property = Property::factory()->create(['concierge_id' => $tenant->id, 'client_id' => $client->id]);
    $subscription = app(SubscriptionManager::class)->ensure($tenant->id);

    return compact('tenant', 'manager', 'client', 'property', 'subscription');
}

function calendarPayload(array $context, array $overrides = []): array
{
    return [...['property_id' => $context['property']->id, 'name' => 'Airbnb principal', 'source' => 'airbnb', 'url' => 'https://calendar.example.com/reservations.ics'], ...$overrides];
}

function calendarFeed(array $context, array $overrides = []): CalendarFeed
{
    return CalendarFeed::factory()->create([...['concierge_id' => $context['tenant']->id, 'property_id' => $context['property']->id], ...$overrides]);
}

function calendarDocument(string $events): string
{
    return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n".$events."END:VCALENDAR\r\n";
}

function calendarEvent(string $uid = 'booking-1', string $start = '20261110', string $end = '20261113', string $extra = ''): string
{
    return "BEGIN:VEVENT\r\nUID:".$uid."\r\nDTSTART;VALUE=DATE:".$start."\r\nDTEND;VALUE=DATE:".$end."\r\nSUMMARY:Séjour importé\r\n".$extra."END:VEVENT\r\n";
}

function fakeCalendarHttp(string $body, int $status = 200): void
{
    Http::swap(new Factory);
    $fetch = Mockery::mock(FetchCalendar::class)->makePartial();
    $fetch->shouldReceive('addresses')->andReturn(['93.184.216.34']);
    app()->instance(FetchCalendar::class, $fetch);
    Http::preventStrayRequests();
    Http::fake(['https://calendar.example.com/reservations.ics' => Http::response($body, $status, ['Content-Type' => 'text/calendar'])]);
}

test('connecting a feed imports periods, encrypts its link and does not create missions', function () {
    $c = calendarContext();
    fakeCalendarHttp(calendarDocument(calendarEvent()));
    $this->actingAs($c['manager'])->post(route('calendar-feeds.store'), calendarPayload($c, ['concierge_id' => 999, 'event_count' => 999]))->assertRedirect()->assertSessionHasNoErrors();
    $feed = CalendarFeed::sole();
    expect($feed->concierge_id)->toBe($c['tenant']->id)
        ->and($feed->event_count)->toBe(1)
        ->and($feed->last_synced_at)->not->toBeNull()
        ->and($feed->url)->toBe('https://calendar.example.com/reservations.ics')
        ->and($feed->getRawOriginal('url'))->not->toContain('calendar.example.com')
        ->and($feed->toArray())->not->toHaveKey('url');
    $this->assertDatabaseHas('stays', ['calendar_feed_id' => $feed->id, 'starts_on' => '2026-11-10', 'ends_on' => '2026-11-13', 'kind' => 'unclassified']);
    $this->assertDatabaseCount('missions', 0);
    Http::assertSentCount(1);
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->has('calendarFeeds', 1)->missing('calendarFeeds.0.url'));
});

test('repeated synchronisation updates periods while preserving qualification and missions', function () {
    $this->freezeTime();
    $c = calendarContext();
    $mission = Mission::factory()->create(['concierge_id' => $c['tenant']->id, 'property_id' => $c['property']->id]);
    fakeCalendarHttp(calendarDocument(calendarEvent()));
    $this->actingAs($c['manager'])->post(route('calendar-feeds.store'), calendarPayload($c))->assertRedirect();
    $stay = Stay::sole();
    $stay->update(['kind' => 'stay']);
    $this->travel(2)->minutes();
    fakeCalendarHttp(calendarDocument(calendarEvent(end: '20261115')));
    $this->post(route('calendar-feeds.sync', CalendarFeed::sole()->id))->assertRedirect()->assertSessionHasNoErrors();
    expect($stay->fresh()->ends_on)->toBe('2026-11-15')->and($stay->fresh()->kind)->toBe('stay');
    $this->assertDatabaseCount('stays', 1);
    $this->assertDatabaseHas('missions', ['id' => $mission->id, 'status' => $mission->status, 'scheduled_at' => $mission->getRawOriginal('scheduled_at')]);
    Http::assertSentCount(1);
});

test('identical external identifiers remain isolated between feeds and file imports', function () {
    $c = calendarContext();
    fakeCalendarHttp(calendarDocument(calendarEvent()));
    app(ImportCalendar::class)->handle($c['tenant']->id, $c['property']->id, 'airbnb', calendarDocument(calendarEvent()));
    $this->actingAs($c['manager'])->post(route('calendar-feeds.store'), calendarPayload($c))->assertRedirect();
    Http::assertSentCount(1);
    Http::fake(['https://calendar.example.com/second.ics' => Http::response(calendarDocument(calendarEvent()))]);
    $this->post(route('calendar-feeds.store'), calendarPayload($c, ['name' => 'Second calendrier', 'url' => 'https://calendar.example.com/second.ics']))->assertRedirect();
    $this->assertDatabaseCount('stays', 3);
    $this->assertDatabaseHas('stays', ['external_uid' => 'booking-1', 'calendar_feed_id' => null]);
    Http::assertSentCount(1);
});

test('the same calendar cannot be connected twice to the same property', function () {
    $c = calendarContext();
    $feed = calendarFeed($c);
    Http::preventStrayRequests();
    $this->actingAs($c['manager'])->post(route('calendar-feeds.store'), calendarPayload($c))->assertSessionHasErrors('url');
    $second = calendarFeed($c, ['url' => 'https://calendar.example.com/second.ics']);
    $this->post(route('calendar-feeds.update', $second->id), ['name' => 'Duplicate', 'url' => $feed->url, 'is_active' => true])->assertSessionHasErrors('url');
    expect($second->fresh()->url)->toBe('https://calendar.example.com/second.ics');
    $this->assertDatabaseCount('calendar_feeds', 2);
    Http::assertNothingSent();
});

test('the scheduler continues with healthy calendars after a supplier fails', function () {
    $c = calendarContext();
    $failed = calendarFeed($c);
    $healthy = calendarFeed($c, ['url' => 'https://calendar.example.com/healthy.ics']);
    fakeCalendarHttp('Unavailable', 503);
    Http::fake([$healthy->url => Http::response(calendarDocument(calendarEvent()))]);
    $this->artisan('app:sync-calendars')->assertFailed();
    expect($failed->fresh()->last_error)->not->toBeNull()->and($healthy->fresh()->last_synced_at)->not->toBeNull();
    $this->assertDatabaseCount('stays', 1);
    Http::assertSentCount(2);
});

test('removed and cancelled periods are archived and reappearing periods are restored', function (string $replacement) {
    $this->freezeTime();
    $c = calendarContext();
    fakeCalendarHttp(calendarDocument(calendarEvent()));
    $this->actingAs($c['manager'])->post(route('calendar-feeds.store'), calendarPayload($c))->assertRedirect();
    $stay = Stay::sole();
    $manual = Stay::factory()->create(['concierge_id' => $c['tenant']->id, 'property_id' => $c['property']->id]);
    $this->travel(2)->minutes();
    fakeCalendarHttp(calendarDocument($replacement));
    $feed = CalendarFeed::sole();
    $this->post(route('calendar-feeds.sync', $feed->id))->assertRedirect()->assertSessionHasNoErrors();
    expect($stay->fresh()->archived_at)->not->toBeNull()->and($manual->fresh()->archived_at)->toBeNull()->and($feed->fresh()->event_count)->toBe(0);
    $this->travel(2)->minutes();
    fakeCalendarHttp(calendarDocument(calendarEvent()));
    $this->post(route('calendar-feeds.sync', $feed->id))->assertRedirect()->assertSessionHasNoErrors();
    expect($stay->fresh()->archived_at)->toBeNull();
    $this->assertDatabaseCount('stays', 2);
    Http::assertSentCount(1);
})->with(['empty feed' => [''], 'cancellation' => ["BEGIN:VEVENT\r\nUID:booking-1\r\nSTATUS:CANCELLED\r\nEND:VEVENT\r\n"]]);

test('invalid feeds preserve the last valid periods and record a safe error', function (string $body, int $status) {
    $this->freezeTime();
    $c = calendarContext();
    fakeCalendarHttp(calendarDocument(calendarEvent()));
    $this->actingAs($c['manager'])->post(route('calendar-feeds.store'), calendarPayload($c))->assertRedirect();
    $feed = CalendarFeed::sole();
    $lastSuccess = $feed->last_synced_at->toIso8601String();
    $this->travel(2)->minutes();
    fakeCalendarHttp($body, $status);
    $this->post(route('calendar-feeds.sync', $feed->id))->assertSessionHasErrors('calendar');
    expect($feed->fresh()->last_error)->not->toBeNull()
        ->and($feed->fresh()->last_error)->not->toContain('https://')
        ->and($feed->fresh()->last_synced_at->toIso8601String())->toBe($lastSuccess)
        ->and(Stay::sole()->archived_at)->toBeNull()
        ->and(Stay::sole()->ends_on)->toBe('2026-11-13');
    Http::assertSentCount(1);
})->with([
    'unavailable supplier' => ['unavailable', 503],
    'login page' => ['<html>Sign in</html>', 200],
    'partial download' => ["BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:broken\r\nEND:VCALENDAR", 200],
    'invalid date' => [calendarDocument(calendarEvent(end: '20260230')), 200],
    'recurring events' => [calendarDocument(calendarEvent(extra: "RRULE:FREQ=DAILY\r\n")), 200],
    'duplicate identifiers' => [calendarDocument(calendarEvent().calendarEvent()), 200],
    'oversized feed' => [str_repeat('x', FetchCalendar::MAX_BYTES + 1), 200],
]);

test('network failures do not leak the private link or discard periods', function () {
    $c = calendarContext();
    $feed = calendarFeed($c);
    $fetch = Mockery::mock(FetchCalendar::class)->makePartial();
    $fetch->shouldReceive('addresses')->andReturn(['93.184.216.34']);
    app()->instance(FetchCalendar::class, $fetch);
    Http::preventStrayRequests();
    Http::fake([$feed->url => Http::failedConnection('secret supplier link')]);
    $this->actingAs($c['manager'])->post(route('calendar-feeds.sync', $feed->id))->assertSessionHasErrors('calendar');
    expect($feed->fresh()->last_error)->not->toContain('secret', $feed->url);
    Http::assertSentCount(1);
});

test('private network destinations are rejected before an HTTP request', function (string $address) {
    $c = calendarContext();
    $feed = calendarFeed($c);
    $fetch = Mockery::mock(FetchCalendar::class)->makePartial();
    $fetch->shouldReceive('addresses')->andReturn([$address]);
    app()->instance(FetchCalendar::class, $fetch);
    Http::preventStrayRequests();
    $this->actingAs($c['manager'])->post(route('calendar-feeds.sync', $feed->id))->assertSessionHasErrors('calendar');
    expect($feed->fresh()->last_synced_at)->toBeNull();
    Http::assertNothingSent();
})->with(['127.0.0.1', '10.0.0.1', '169.254.169.254', '192.168.1.1', '0.0.0.0', '::1', '::ffff:127.0.0.1', 'fc00::1', 'fe80::1']);

test('redirects are revalidated and cannot reach an internal address', function () {
    $c = calendarContext();
    $feed = calendarFeed($c);
    Http::preventStrayRequests();
    Http::fake([$feed->url => Http::response('', 302, ['Location' => 'https://127.0.0.1/private'])]);
    $fetch = Mockery::mock(FetchCalendar::class)->makePartial();
    $fetch->shouldReceive('addresses')->with('calendar.example.com')->andReturn(['93.184.216.34']);
    $fetch->shouldReceive('addresses')->with('127.0.0.1')->andReturn(['127.0.0.1']);
    app()->instance(FetchCalendar::class, $fetch);
    $this->actingAs($c['manager'])->post(route('calendar-feeds.sync', $feed->id))->assertSessionHasErrors('calendar');
    Http::assertSentCount(1);
});

test('unsafe URL formats cannot be saved', function (string $url) {
    $c = calendarContext();
    Http::preventStrayRequests();
    $this->actingAs($c['manager'])->post(route('calendar-feeds.store'), calendarPayload($c, ['url' => $url]))->assertSessionHasErrors('url');
    $this->assertDatabaseCount('calendar_feeds', 0);
    Http::assertNothingSent();
})->with(['file:///etc/passwd', 'http://calendar.example.com/test.ics', 'https://user:pass@calendar.example.com/test.ics', 'https://calendar.example.com:8443/test.ics', 'https://calendar.example.com/test.ics#fragment']);

test('a manager can suspend and replace a feed without exposing its previous link', function () {
    $c = calendarContext();
    $feed = calendarFeed($c);
    Http::preventStrayRequests();
    $this->actingAs($c['manager'])->post(route('calendar-feeds.update', $feed->id), ['name' => 'Suspendu', 'url' => '', 'is_active' => false, 'concierge_id' => 999, 'property_id' => 999, 'source' => 'booking'])->assertRedirect()->assertSessionHasNoErrors();
    expect($feed->fresh()->is_active)->toBeFalse()->and($feed->fresh()->url)->toBe($feed->url)->and($feed->fresh()->property_id)->toBe($c['property']->id)->and($feed->fresh()->source)->toBe('airbnb');
    $this->post(route('calendar-feeds.sync', $feed->id))->assertSessionHasErrors('calendar');
    $this->post(route('calendar-feeds.update', $feed->id), ['name' => 'Réactivé', 'url' => 'webcal://calendar.example.com/new.ics', 'is_active' => true])->assertRedirect()->assertSessionHasNoErrors();
    expect($feed->fresh()->url)->toBe('https://calendar.example.com/new.ics')->and($feed->fresh()->is_active)->toBeTrue();
    Http::assertNothingSent();
});

test('workers clients and coordinators cannot manage calendar connections', function (string $role) {
    $c = calendarContext();
    $feed = calendarFeed($c);
    $user = User::factory()->create();
    Membership::factory()->create(['concierge_id' => $c['tenant']->id, 'user_id' => $user->id, 'role' => $role, 'client_id' => $role === 'client' ? $c['client']->id : null, 'company' => $role === 'coordinator' ? 'Prestataire' : null]);
    Http::preventStrayRequests();
    $this->actingAs($user)->post(route('calendar-feeds.store'), calendarPayload($c))->assertForbidden();
    $this->post(route('calendar-feeds.update', $feed->id), ['name' => 'Attack', 'is_active' => false])->assertForbidden();
    $this->post(route('calendar-feeds.sync', $feed->id))->assertForbidden();
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('calendarFeeds', []));
    expect($feed->fresh()->name)->toBe($feed->name);
    Http::assertNothingSent();
})->with(['worker', 'client', 'coordinator']);

test('calendar connections and properties cannot be accessed across tenants', function () {
    $c = calendarContext();
    $other = calendarContext();
    $feed = calendarFeed($other);
    Http::preventStrayRequests();
    $this->actingAs($c['manager'])->post(route('calendar-feeds.sync', $feed->id))->assertNotFound();
    $this->post(route('calendar-feeds.update', $feed->id), ['name' => 'Attack', 'is_active' => false])->assertNotFound();
    $this->post(route('calendar-feeds.store'), calendarPayload($c, ['property_id' => $other['property']->id]))->assertSessionHasErrors('property_id');
    expect($feed->fresh()->is_active)->toBeTrue();
    Http::assertNothingSent();
});

test('guests are redirected to sign in for calendar actions', function () {
    $this->post(route('calendar-feeds.store'))->assertRedirect(route('login'));
    $this->post(route('calendar-feeds.update', 1))->assertRedirect(route('login'));
    $this->post(route('calendar-feeds.sync', 1))->assertRedirect(route('login'));
});

test('subscription restrictions prevent manual calendar actions', function () {
    $c = calendarContext();
    $feed = calendarFeed($c);
    $c['subscription']->update(['feature_overrides' => ['calendar_import' => false]]);
    Http::preventStrayRequests();
    $this->actingAs($c['manager'])->postJson(route('calendar-feeds.store'), calendarPayload($c))->assertUnprocessable()->assertJsonValidationErrors('subscription');
    $this->postJson(route('calendar-feeds.sync', $feed->id))->assertUnprocessable()->assertJsonValidationErrors('subscription');
    $this->postJson(route('calendar-feeds.update', $feed->id), ['name' => 'No', 'is_active' => false])->assertUnprocessable()->assertJsonValidationErrors('subscription');
    Http::assertNothingSent();
});

test('scheduled synchronisation respects paused archived and ineligible accounts', function (string $reason) {
    $c = calendarContext();
    $feed = calendarFeed($c);
    match ($reason) {
        'paused' => $feed->update(['is_active' => false]),
        'archived' => $c['property']->forceFill(['archived_at' => now()])->save(),
        'expired' => $c['subscription']->update(['expires_at' => now()->subDay()]),
        'suspended' => $c['subscription']->update(['status' => 'suspended']),
        'feature' => $c['subscription']->update(['feature_overrides' => ['calendar_import' => false]]),
        'recent' => $feed->forceFill(['last_attempt_at' => now()])->save(),
    };
    Http::preventStrayRequests();
    $this->artisan('app:sync-calendars')->assertSuccessful();
    expect($feed->fresh()->last_synced_at)->toBeNull();
    Http::assertNothingSent();
})->with(['paused', 'archived', 'expired', 'suspended', 'feature', 'recent']);

test('the scheduler retries due feeds and manual actions respect the cooldown and shared lock', function () {
    $this->freezeTime();
    $c = calendarContext();
    $feed = calendarFeed($c);
    fakeCalendarHttp(calendarDocument(calendarEvent()));
    $this->artisan('app:sync-calendars')->assertSuccessful();
    $this->actingAs($c['manager'])->post(route('calendar-feeds.sync', $feed->id))->assertSessionHasErrors('calendar');
    $this->travel(16)->minutes();
    $lock = Cache::lock('calendar-feed:'.$feed->id, 120);
    $lock->get();
    try {
        $this->post(route('calendar-feeds.sync', $feed->id))->assertSessionHasErrors('calendar');
    } finally {
        $lock->release();
    }
    $this->artisan('app:sync-calendars')->assertSuccessful();
    Http::assertSentCount(2);
    $this->assertDatabaseCount('stays', 1);
});

test('ineligible accounts cannot starve healthy calendars from the scheduled batch', function () {
    $blocked = calendarContext();
    $blocked['subscription']->update(['feature_overrides' => ['calendar_import' => false]]);
    for ($index = 0; $index < 101; $index++) {
        calendarFeed($blocked, ['url' => 'https://calendar.example.com/blocked-'.$index.'.ics']);
    }
    $healthy = calendarContext();
    $feed = calendarFeed($healthy);
    fakeCalendarHttp(calendarDocument(calendarEvent()));
    $this->artisan('app:sync-calendars')->assertSuccessful();
    expect($feed->fresh()->last_synced_at)->not->toBeNull();
    $this->assertDatabaseCount('stays', 1);
    Http::assertSentCount(1);
});

test('scheduled calendars honor explicitly enabled subscription options', function () {
    $c = calendarContext();
    $terms = $c['subscription']->terms;
    $terms['features']['calendar_import'] = false;
    $c['subscription']->update(['terms' => $terms, 'feature_overrides' => ['calendar_import' => true]]);
    $feed = calendarFeed($c);
    fakeCalendarHttp(calendarDocument(calendarEvent()));
    $this->artisan('app:sync-calendars')->assertSuccessful();
    expect($feed->fresh()->last_synced_at)->not->toBeNull();
    Http::assertSentCount(1);
});
