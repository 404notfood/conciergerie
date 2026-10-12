<?php
namespace App\Http\Middleware;
use App\Actions\Concierge\{SubscriptionManager,WorkspaceAccess};
use App\Models\Membership;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
class EnsureSubscription {
    public function __construct(private SubscriptionManager $subscriptions, private WorkspaceAccess $access) {}
    public function handle(Request $request, Closure $next): Response {
        if (!$request->user()->is_admin && Membership::where('user_id',$request->user()->id)->exists()) {
            $member=$this->access->membership($request);$subscription=$this->subscriptions->ensure($member->concierge_id);
            if (!$this->subscriptions->usable($subscription)) {
                if($request->expectsJson()||$request->isMethod('post')){abort(402,'Votre abonnement est suspendu ou arrivé à échéance. Les preuves locales sont conservées.');}
                return Inertia::render('workspace/subscription',['subscription'=>$subscription,'limits'=>$this->subscriptions->limits($subscription),'features'=>$this->subscriptions->features($subscription),'usage'=>$this->subscriptions->usage($member->concierge_id),'monthly_price_cents'=>$this->subscriptions->price($subscription),'limitLabels'=>SubscriptionManager::LIMITS,'featureLabels'=>SubscriptionManager::FEATURES,'blocked'=>true])->toResponse($request);
            }
        } return $next($request);
    }
}
