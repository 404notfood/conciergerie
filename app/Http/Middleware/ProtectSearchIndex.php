<?php

namespace App\Http\Middleware;

use App\Actions\Seo\SeoManager;
use App\Models\SeoPage;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProtectSearchIndex
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $seo = app(SeoManager::class);
        $public = $request->routeIs('home', 'pricing', 'resources', 'marketing.article');
        $page = $public ? SeoPage::where('path', '/'.trim($request->path(), '/'))->first() : null;
        if (! $seo->enabled() || ! $public || ! $page || $page->noindex || $response->getStatusCode() !== 200) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
