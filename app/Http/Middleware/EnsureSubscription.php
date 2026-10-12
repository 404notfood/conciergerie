<?php

namespace App\Http\Middleware;

use App\Actions\Concierge\SubscriptionManager;
use App\Actions\Concierge\WorkspaceAccess;
use App\Models\Membership;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscription
{
    public function __construct(private SubscriptionManager $subscriptions, private WorkspaceAccess $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()->is_admin && Membership::where('user_id', $request->user()->id)->exists()) {
            $member = $this->access->membership($request);
            $subscription = $this->subscriptions->ensure($member->concierge_id);
            $features = $this->subscriptions->features($subscription);
            if (($member->role === 'client' && ! ($features['client_portal'] ?? false)) || ($member->role === 'coordinator' && ! ($features['subcontracting'] ?? false))) {
                if ($request->expectsJson() || ! $request->isMethod('get')) {
                    abort(403, 'Cette option n’est pas incluse dans l’abonnement de votre conciergerie.');
                }

                return Inertia::render('workspace/access-blocked')->toResponse($request);
            }
            if (! $this->subscriptions->usable($subscription)) {
                if ($request->expectsJson()) {
                    abort(402, 'Votre abonnement est suspendu ou arrivé à échéance. Les preuves locales sont conservées.');
                }
                if (! $request->isMethod('get')) {
                    return redirect()->route($member->role === 'manager' ? 'subscription.show' : 'dashboard');
                }
                if ($member->role !== 'manager') {
                    return Inertia::render('workspace/access-blocked')->toResponse($request);
                }

                return Inertia::render('workspace/subscription', ['subscription' => $subscription, 'limits' => $this->subscriptions->limits($subscription), 'features' => $this->subscriptions->features($subscription), 'usage' => $this->subscriptions->usage($member->concierge_id), 'monthly_price_cents' => $this->subscriptions->price($subscription), 'limitLabels' => SubscriptionManager::LIMITS, 'featureLabels' => SubscriptionManager::FEATURES, 'blocked' => true])->toResponse($request);
            }
        }

        return $next($request);
    }
}
