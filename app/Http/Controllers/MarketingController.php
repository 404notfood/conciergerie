<?php

namespace App\Http\Controllers;

use App\Actions\Concierge\SubscriptionManager;
use App\Actions\Seo\SeoManager;
use App\Models\SeoPage;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class MarketingController extends Controller
{
    public function __construct(private SeoManager $seo) {}

    public function home(): Response
    {
        return $this->page('/');
    }

    public function pricing(): Response
    {
        return $this->page('/tarifs');
    }

    public function resources(): Response
    {
        return $this->page('/ressources');
    }

    public function article(string $category, string $slug): Response
    {
        return $this->page('/'.$category.'/'.$slug);
    }

    private function page(string $path): Response
    {
        $page = SeoPage::where('path', $path)->where('status', 'published')->whereNotNull('published_at')->firstOrFail();
        $props = ['content' => $this->seo->content($page), 'seo' => $this->seo->metadata($page), 'publicLinks' => $this->seo->links()];
        if ($page->kind === 'pricing') {
            $props += ['plans' => SubscriptionPlan::where('is_active', true)->where('is_public', true)->orderBy('monthly_price_cents')->get(), 'limitLabels' => SubscriptionManager::LIMITS, 'featureLabels' => SubscriptionManager::FEATURES];
        }
        $component = match ($page->kind) {
            'home' => 'welcome',
            'pricing' => 'pricing',
            default => 'marketing/content',
        };

        return Inertia::render($component, $props)->rootView('marketing');
    }

    public function robots(): HttpResponse
    {
        $private = ['/admin', '/dashboard', '/terrain', '/terrain-data', '/abonnement', '/settings', '/login', '/register', '/forgot-password', '/reset-password', '/two-factor-challenge', '/photos/', '/clients', '/properties', '/members', '/missions', '/reports', '/incidents', '/messages', '/requests', '/workspace', '/stays', '/templates'];
        $rules = $this->seo->enabled() ? "Allow: /\n".implode("\n", array_map(fn (string $path) => 'Disallow: '.$path, $private))."\n" : "Disallow: /\n";
        $text = "User-agent: *\n".$rules;
        $settings = $this->seo->settings();
        foreach (['OAI-SearchBot' => $settings->allow_ai_search, 'GPTBot' => $settings->allow_ai_training] as $agent => $allowed) {
            $text .= "\nUser-agent: ".$agent."\n".($allowed ? $rules : "Disallow: /\n");
        }
        if ($this->seo->enabled()) {
            $text .= "\nSitemap: ".$this->seo->url('/sitemap.xml')."\n";
        }

        return response($text, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(): HttpResponse
    {
        $urls = '';
        if ($this->seo->enabled()) {
            foreach ($this->seo->published()->where('noindex', false) as $page) {
                $url = htmlspecialchars($this->seo->url($page->path), ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $urls .= '<url><loc>'.$url.'</loc><lastmod>'.$page->updated_at->toIso8601String().'</lastmod></url>';
            }
        }

        return response('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$urls.'</urlset>', 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function llms(): HttpResponse
    {
        $text = '# '.$this->seo->name()."\n\n";
        if (! $this->seo->enabled()) {
            $text .= "Site en préparation. Indexation non activée.\n";
        } else {
            $text .= "Application web de gestion de conciergerie : missions, équipes, lieux, comptes rendus et espace client.\n\n## Pages publiques\n";
            foreach ($this->seo->published()->where('noindex', false) as $page) {
                $text .= '- ['.$this->seo->text($page->heading).']('.$this->seo->url($page->path).'): '.preg_replace('/\s+/', ' ', $this->seo->text($page->intro))."\n";
            }
        }

        return response($text, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
