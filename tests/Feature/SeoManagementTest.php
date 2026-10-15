<?php

use App\Actions\Seo\SeoManager;
use App\Models\SeoPage;
use App\Models\SeoSetting;
use App\Models\User;
use Database\Seeders\SeoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(SeoSeeder::class);
});

function seoAdmin(): User
{
    $user = User::factory()->create();
    $user->forceFill(['is_admin' => true])->save();

    return $user;
}

function seoSettingsData(array $overrides = []): array
{
    return [...SeoSetting::first()->toArray(), ...$overrides];
}

function seoPageData(SeoPage $page, array $overrides = []): array
{
    return [...$page->toArray(), ...$overrides];
}

test('public content metadata and FAQ are present in the initial HTML without JavaScript', function () {
    $page = SeoPage::where('path', '/fonctionnalites/planning-missions')->first();
    $response = $this->get($page->path)->assertOk();
    $response->assertSee('<h1>'.$page->heading.'</h1>', false)
        ->assertSee('<h2>'.$page->sections[0]['heading'].'</h2>', false)
        ->assertSee('<summary>'.$page->faq[0]['question'].'</summary>', false)
        ->assertSee('name="description"', false)
        ->assertSee('rel="canonical"', false)
        ->assertSee('application/ld+json', false);
    $response->assertInertia(fn (Assert $inertia) => $inertia->component('marketing/content')->where('content.heading', $page->heading));
});

test('the ConcierPilot identity upgrade updates public metadata and preserves SEO preferences', function () {
    SeoSetting::first()->update(['site_name' => 'Tout en Main', 'public_url' => 'https://conciergerie.test', 'indexing_enabled' => false, 'google_verification' => 'existing-google-token', 'contact_email' => 'contact@example.test']);
    $migration = require database_path('migrations/2026_10_15_120245_update_public_identity_to_concierpilot.php');

    $migration->up();

    $this->get('/')->assertOk()->assertSee('ConcierPilot')->assertSee('href="https://concierpilot.fr/"', false);
    $this->assertDatabaseHas('seo_settings', ['id' => 1, 'site_name' => 'ConcierPilot', 'public_url' => 'https://concierpilot.fr', 'indexing_enabled' => false, 'google_verification' => 'existing-google-token', 'contact_email' => 'contact@example.test']);
});

test('blank admin identity settings fall back to APP_NAME and APP_URL configuration', function () {
    config(['app.name' => 'Mon Application', 'app.url' => 'https://future-domaine.fr']);
    $response = $this->get('/')->assertOk()->assertSee('Mon Application')->assertSee('https://future-domaine.fr/');
    $response->assertInertia(fn (Assert $page) => $page->where('seo.site_name', 'Mon Application')->where('seo.canonical', 'https://future-domaine.fr/')->where('name', 'Mon Application'));
});

test('local installations stay excluded even when indexing is requested', function () {
    SeoSetting::first()->update(['indexing_enabled' => true, 'public_url' => 'https://conciergerie.test']);
    $this->get('/')->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertSee('content="noindex, nofollow"', false);
    $this->get('/robots.txt')->assertSee("User-agent: *\nDisallow: /", false)->assertDontSee('Sitemap:', false);
    $this->get('/sitemap.xml')->assertDontSee('<url>', false);
});

test('indexing requires production as well as a public domain', function () {
    SeoSetting::first()->update(['indexing_enabled' => true, 'public_url' => 'https://mon-application.fr']);
    expect(app(SeoManager::class)->enabled())->toBeFalse();
    $this->app['env'] = 'production';
    expect(app(SeoManager::class)->enabled())->toBeTrue();
    $this->get('/')->assertHeaderMissing('X-Robots-Tag')->assertSee('index, follow, max-image-preview:large');
});

test('sitemap contains only published indexable public pages and removes an unpublished page', function () {
    $this->app['env'] = 'production';
    SeoSetting::first()->update(['indexing_enabled' => true, 'public_url' => 'https://mon-application.fr']);
    $draft = SeoPage::factory()->create(['status' => 'draft', 'published_at' => null]);
    $hidden = SeoPage::factory()->create(['noindex' => true]);
    $public = SeoPage::factory()->create();
    $this->get('/sitemap.xml')->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->assertSee('https://mon-application.fr'.$public->path, false)->assertDontSee($draft->path)->assertDontSee($hidden->path)->assertDontSee('/admin')->assertDontSee('/dashboard')->assertDontSee('/photos/');
    $this->actingAs(seoAdmin())->withSession(['_token' => 'seo-test-token'])->post(route('admin.seo.pages.update', $public), [...seoPageData($public, ['status' => 'draft']), '_token' => 'seo-test-token'])->assertRedirect()->assertSessionHasNoErrors();
    $this->get('/sitemap.xml')->assertDontSee($public->path);
    $this->get($public->path)->assertNotFound();
});

test('ChatGPT search and training crawler permissions are independent', function () {
    $this->app['env'] = 'production';
    SeoSetting::first()->update(['indexing_enabled' => true, 'public_url' => 'https://mon-application.fr', 'allow_ai_search' => true, 'allow_ai_training' => false]);
    $this->get('/robots.txt')->assertSee("User-agent: OAI-SearchBot\nAllow: /", false)->assertSee("User-agent: GPTBot\nDisallow: /", false)->assertSee('Disallow: /admin', false)->assertSee('Sitemap: https://mon-application.fr/sitemap.xml', false);
});

test('private application pages and authentication responses are never indexable', function () {
    $this->app['env'] = 'production';
    SeoSetting::first()->update(['indexing_enabled' => true, 'public_url' => 'https://mon-application.fr']);
    $this->get('/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $this->get('/dashboard')->assertRedirect()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $this->actingAs(seoAdmin())->get('/admin?view=seo')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

test('unpublished content is absent from public routes navigation and machine-readable summaries', function () {
    $this->app['env'] = 'production';
    SeoSetting::first()->update(['indexing_enabled' => true, 'public_url' => 'https://mon-application.fr']);
    $draft = SeoPage::factory()->create(['status' => 'draft', 'published_at' => null, 'heading' => 'Contenu confidentiel en préparation']);
    $this->get($draft->path)->assertNotFound();
    $this->get('/ressources')->assertDontSee('Contenu confidentiel en préparation');
    $this->get('/llms.txt')->assertDontSee('Contenu confidentiel en préparation');
});

test('ordinary users and guests cannot modify SEO settings or content', function () {
    $this->post(route('admin.seo.settings'), seoSettingsData(['site_name' => 'Tentative']))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->post(route('admin.seo.settings'), seoSettingsData(['site_name' => 'Tentative']))->assertForbidden();
    $this->post(route('admin.seo.pages.store'), [])->assertForbidden();
    $page = SeoPage::first();
    $this->post(route('admin.seo.pages.update', $page), seoPageData($page, ['title' => 'Tentative']))->assertForbidden();
    expect(SeoSetting::first()->site_name)->toBeNull()->and($page->fresh()->title)->not->toBe('Tentative');
});

test('admin identity verification and domain changes reach all public metadata', function () {
    $this->actingAs(seoAdmin())->post(route('admin.seo.settings'), seoSettingsData(['site_name' => 'La Clé', 'public_url' => 'https://lacle.fr', 'google_verification' => 'google-token_123', 'bing_verification' => 'BING123']))->assertRedirect()->assertSessionHasNoErrors();
    $this->get('/')->assertSee('La Clé')->assertSee('href="https://lacle.fr/"', false)->assertSee('content="google-token_123"', false)->assertSee('content="BING123"', false);
    $this->assertDatabaseHas('administration_events', ['description' => 'Configuration du référencement modifiée']);
});

test('domain values must be clean HTTPS origins', function (string $url) {
    $this->actingAs(seoAdmin())->post(route('admin.seo.settings'), seoSettingsData(['public_url' => $url]))->assertSessionHasErrors('public_url');
    expect(SeoSetting::first()->public_url)->toBeNull();
})->with(['http://example.com', 'https://example.com/path', 'https://example.com?token=secret', 'https://user:password@example.com', 'https://example.com:444', 'javascript:alert(1)']);

test('admin updates change visible content and structured FAQ without running a frontend build', function () {
    $page = SeoPage::where('path', '/fonctionnalites/planning-missions')->first();
    $data = seoPageData($page, ['heading' => 'Mon nouveau planning', 'title' => 'Titre édité', 'description' => 'Description éditée et enregistrée dans la base de données.', 'faq' => [['question' => 'Question publiée ?', 'answer' => 'Réponse publiée.']]]);
    $this->actingAs(seoAdmin())->post(route('admin.seo.pages.update', $page), $data)->assertRedirect()->assertSessionHasNoErrors();
    $response = $this->get($page->path)->assertOk()->assertSee('<h1>Mon nouveau planning</h1>', false)->assertSee('<summary>Question publiée ?</summary>', false);
    $response->assertInertia(fn (Assert $props) => $props->where('seo.title', 'Titre édité')->where('seo.schema.@graph.4.mainEntity.0.name', 'Question publiée ?'));
});

test('public text and JSON LD safely escape executable markup', function () {
    $page = SeoPage::where('path', '/fonctionnalites/planning-missions')->first();
    $page->update(['heading' => '<script>alert("x")</script>', 'intro' => '</script><script>alert("x")</script>', 'faq' => [['question' => 'Test', 'answer' => '</script><script>alert("x")</script>']]]);
    $this->get($page->path)->assertOk()->assertDontSee('<script>alert("x")</script>', false)->assertSee('&lt;script&gt;', false)->assertSee('\u003C/script\u003E', false);
});

test('admin can create a useful page but cannot publish an empty page or overwrite private routes', function () {
    $data = seoPageData(SeoPage::first(), ['path' => '/guides/preparer-une-mission', 'title' => 'Préparer une mission', 'heading' => 'Une mission bien préparée']);
    $this->actingAs(seoAdmin())->post(route('admin.seo.pages.store'), $data)->assertRedirect()->assertSessionHasNoErrors();
    $this->get('/guides/preparer-une-mission')->assertOk()->assertSee('<h1>Une mission bien préparée</h1>', false);
    $this->post(route('admin.seo.pages.store'), [...$data, 'path' => '/dashboard'])->assertSessionHasErrors('path');
    $this->post(route('admin.seo.pages.store'), [...$data, 'path' => '/guides/page-vide', 'sections' => []])->assertSessionHasErrors('sections');
});

test('the base pages remain published and edited content survives reseeding', function () {
    $page = SeoPage::where('path', '/')->first();
    $this->actingAs(seoAdmin())->post(route('admin.seo.pages.update', $page), seoPageData($page, ['status' => 'draft']))->assertSessionHasErrors('status');
    $page->update(['heading' => 'Titre choisi par l’administrateur']);
    $this->seed(SeoSeeder::class);
    expect($page->fresh()->heading)->toBe('Titre choisi par l’administrateur');
});
