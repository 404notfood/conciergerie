<?php

namespace App\Actions\Seo;

use App\Models\SeoPage;
use App\Models\SeoSetting;
use Illuminate\Database\Eloquent\Collection;

class SeoManager
{
    private ?SeoSetting $loadedSettings = null;

    public function settings(): SeoSetting
    {
        return $this->loadedSettings ??= SeoSetting::firstOrCreate(['id' => 1]);
    }

    public function name(): string
    {
        return $this->settings()->site_name ?: (string) config('app.name');
    }

    public function baseUrl(): string
    {
        return rtrim($this->settings()->public_url ?: (string) config('app.url'), '/');
    }

    public function isPublicUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (! $host || ! str_contains($host, '.') || preg_match('/(^localhost$|\.test$|\.local$|\.localhost$|\.invalid$|\.example$)/', $host)) {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return (bool) filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        return ! str_ends_with($host, '.internal');
    }

    public function enabled(): bool
    {
        return app()->environment('production') && $this->settings()->indexing_enabled && $this->isPublicUrl($this->baseUrl());
    }

    public function url(string $path): string
    {
        return $this->baseUrl().($path === '/' ? '/' : $path);
    }

    public function text(string $value): string
    {
        return str_replace('{app_name}', $this->name(), $value);
    }

    /** @return Collection<int, SeoPage> */
    public function published(): Collection
    {
        return SeoPage::where('status', 'published')->whereNotNull('published_at')->orderBy('id')->get();
    }

    /** @return array<string, mixed> */
    public function content(SeoPage $page): array
    {
        $data = $page->toArray();
        foreach (['title', 'description', 'heading', 'intro', 'social_title', 'social_description'] as $key) {
            $data[$key] = $this->text((string) ($data[$key] ?? ''));
        }
        $data['sections'] = array_map(fn (array $section) => ['heading' => $this->text($section['heading']), 'text' => $this->text($section['text'])], $page->sections);
        $data['faq'] = array_map(fn (array $faq) => ['question' => $this->text($faq['question']), 'answer' => $this->text($faq['answer'])], $page->faq);

        return $data;
    }

    /** @return array<int, array<string, mixed>> */
    public function links(): array
    {
        return $this->published()->where('kind', 'content')->map(fn (SeoPage $page) => ['path' => $page->path, 'heading' => $this->text($page->heading), 'intro' => $this->text($page->intro)])->values()->all();
    }

    /** @return array<string, mixed> */
    public function metadata(SeoPage $page): array
    {
        $content = $this->content($page);
        $base = $this->url('/');
        $canonical = $this->url($page->path);
        $schema = [
            ['@type' => 'Organization', '@id' => $base.'#organization', 'name' => $this->name(), 'url' => $base],
            ['@type' => 'WebSite', '@id' => $base.'#website', 'name' => $this->name(), 'url' => $base, 'inLanguage' => 'fr-FR', 'publisher' => ['@id' => $base.'#organization']],
            ['@type' => 'WebPage', '@id' => $canonical.'#webpage', 'url' => $canonical, 'name' => $content['title'], 'description' => $content['description'], 'inLanguage' => 'fr-FR', 'isPartOf' => ['@id' => $base.'#website'], 'dateModified' => $page->updated_at->toIso8601String()],
        ];
        if ($this->settings()->contact_email) {
            $schema[0]['email'] = $this->settings()->contact_email;
        }
        if ($page->kind === 'home') {
            $schema[] = ['@type' => 'SoftwareApplication', 'name' => $this->name(), 'url' => $base, 'applicationCategory' => 'BusinessApplication', 'operatingSystem' => 'Web', 'description' => $content['description'], 'featureList' => ['Planning des missions', 'Équipes et prestataires', 'Comptes rendus avec photos', 'Espace client', 'Préparation de missions hors connexion']];
        }
        if ($page->path !== '/') {
            $breadcrumbs = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Accueil', 'item' => $base]];
            if ($page->kind === 'content') {
                $breadcrumbs[] = ['@type' => 'ListItem', 'position' => 2, 'name' => 'Ressources', 'item' => $this->url('/ressources')];
            }
            $breadcrumbs[] = ['@type' => 'ListItem', 'position' => count($breadcrumbs) + 1, 'name' => $page->kind === 'hub' ? 'Ressources' : $content['heading'], 'item' => $canonical];
            $schema[] = ['@type' => 'BreadcrumbList', 'itemListElement' => $breadcrumbs];
        }
        if ($page->faq !== []) {
            $schema[] = ['@type' => 'FAQPage', 'mainEntity' => array_map(fn (array $faq) => ['@type' => 'Question', 'name' => $this->text($faq['question']), 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $this->text($faq['answer'])]], $page->faq)];
        }

        return ['title' => $content['title'], 'description' => $content['description'], 'canonical' => $canonical, 'robots' => $this->enabled() && ! $page->noindex ? 'index, follow, max-image-preview:large' : 'noindex, nofollow', 'site_name' => $this->name(), 'social_title' => $content['social_title'] ?: $content['title'], 'social_description' => $content['social_description'] ?: $content['description'], 'social_image' => $this->settings()->social_image, 'google_verification' => $this->settings()->google_verification, 'bing_verification' => $this->settings()->bing_verification, 'schema' => ['@context' => 'https://schema.org', '@graph' => $schema]];
    }

    /** @return array<string, mixed> */
    public function administration(): array
    {
        $pages = SeoPage::orderBy('id')->get();
        $titles = $pages->groupBy(fn (SeoPage $page) => $this->text($page->title));
        $descriptions = $pages->groupBy(fn (SeoPage $page) => $this->text($page->description));

        return ['settings' => $this->settings(), 'effective_name' => $this->name(), 'effective_url' => $this->baseUrl(), 'environment' => app()->environment(), 'indexing_active' => $this->enabled(), 'public_domain' => $this->isPublicUrl($this->baseUrl()), 'pages' => $pages->map(function (SeoPage $page) use ($titles, $descriptions) {
            $warnings = [];
            if (mb_strlen($this->text($page->title)) > 65) {
                $warnings[] = 'Titre long : vérifiez l’aperçu.';
            }
            if (mb_strlen($this->text($page->description)) > 170 || mb_strlen($this->text($page->description)) < 90) {
                $warnings[] = 'Description à ajuster pour un aperçu plus clair.';
            }
            if ($titles[$this->text($page->title)]->count() > 1) {
                $warnings[] = 'Titre identique à une autre page.';
            }
            if ($descriptions[$this->text($page->description)]->count() > 1) {
                $warnings[] = 'Description identique à une autre page.';
            }
            if ($page->kind === 'content' && $page->sections === []) {
                $warnings[] = 'Ajoutez du contenu utile avant publication.';
            }

            return [...$page->toArray(), 'resolved_title' => $this->text($page->title), 'resolved_description' => $this->text($page->description), 'canonical' => $this->url($page->path), 'warnings' => $warnings];
        })];
    }
}
