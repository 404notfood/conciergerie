<?php

namespace App\Http\Controllers;

use App\Actions\Seo\SeoManager;
use App\Models\SeoPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SeoAdministrationController extends Controller
{
    public function __construct(private SeoManager $seo) {}

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'site_name' => ['nullable', 'string', 'max:120'],
            'public_url' => ['nullable', 'url:https', 'max:255'],
            'indexing_enabled' => ['required', 'boolean'],
            'allow_ai_search' => ['required', 'boolean'],
            'allow_ai_training' => ['required', 'boolean'],
            'google_verification' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'bing_verification' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'social_image' => ['nullable', 'url:https', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
        ]);
        if ($data['public_url'] ?? null) {
            $url = parse_url($data['public_url']);
            if (isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment']) || ! in_array($url['path'] ?? '', ['', '/'], true) || isset($url['port'])) {
                throw ValidationException::withMessages(['public_url' => 'Indiquez seulement le domaine HTTPS, sans chemin, port, identifiant ou paramètres.']);
            }
            $data['public_url'] = rtrim($data['public_url'], '/');
        }
        DB::transaction(function () use ($request, $data) {
            $this->seo->settings()->update($data);
            $this->log($request, 'Configuration du référencement modifiée');
        });

        return back()->with('success', 'Configuration SEO enregistrée. L’indexation nécessite un domaine public et l’environnement production.');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->data($request);
        $data['path'] = $request->validate(['path' => ['required', 'string', 'max:255', 'regex:~^/(fonctionnalites|solutions|guides)/[a-z0-9]+(?:-[a-z0-9]+)*$~', Rule::unique('seo_pages', 'path')]])['path'];
        $data['kind'] = 'content';
        DB::transaction(function () use ($request, $data) {
            $page = SeoPage::create($data);
            $this->log($request, 'Page publique créée : '.$page->path);
        });

        return back()->with('success', 'Page créée.');
    }

    public function update(Request $request, SeoPage $seoPage): RedirectResponse
    {
        $data = $this->data($request, $seoPage);
        if ($seoPage->kind !== 'content' && $data['status'] !== 'published') {
            throw ValidationException::withMessages(['status' => 'Les pages principales restent publiées. Utilisez « Exclure de l’indexation » pour les retirer des moteurs.']);
        }
        DB::transaction(function () use ($request, $seoPage, $data) {
            $seoPage->update($data);
            $this->log($request, 'Page publique modifiée : '.$seoPage->path);
        });

        return back()->with('success', 'Page mise à jour. Le contenu public et le sitemap reflètent vos modifications.');
    }

    /** @return array<string, mixed> */
    private function data(Request $request, ?SeoPage $page = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string', 'max:500'],
            'heading' => ['required', 'string', 'max:200'],
            'intro' => ['required', 'string', 'max:5000'],
            'keyword' => ['nullable', 'string', 'max:200'],
            'social_title' => ['nullable', 'string', 'max:180'],
            'social_description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'noindex' => ['required', 'boolean'],
            'sections' => ['present', 'array', 'max:20'],
            'sections.*' => ['array:heading,text'],
            'sections.*.heading' => ['required', 'string', 'max:200'],
            'sections.*.text' => ['required', 'string', 'max:12000'],
            'faq' => ['present', 'array', 'max:20'],
            'faq.*' => ['array:question,answer'],
            'faq.*.question' => ['required', 'string', 'max:300'],
            'faq.*.answer' => ['required', 'string', 'max:5000'],
        ]);
        if ($data['status'] === 'published' && ($page === null || $page->kind === 'content') && $data['sections'] === []) {
            throw ValidationException::withMessages(['sections' => 'Une page publiée doit proposer au moins une section de contenu.']);
        }
        $data['published_at'] = $data['status'] === 'published' ? ($page->published_at ?? now()) : null;

        return $data;
    }

    private function log(Request $request, string $description): void
    {
        DB::table('administration_events')->insert(['user_id' => $request->user()->id, 'description' => $description, 'details' => null, 'created_at' => now(), 'updated_at' => now()]);
    }
}
