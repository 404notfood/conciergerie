<?php

namespace App\Http\Controllers;

use App\Actions\Concierge\SubscriptionManager;
use App\Actions\Seo\SeoManager;
use App\Models\Concierge;
use App\Models\Membership;
use App\Models\PlanAddon;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdministrationController extends Controller
{
    public function __construct(private SubscriptionManager $subscriptions) {}

    public function index(Request $request): Response
    {
        $settings = $this->subscriptions->settings();
        $concierges = Concierge::orderByDesc('id')->get()->map(function (Concierge $concierge) {
            $subscription = $this->subscriptions->ensure($concierge->id);

            return ['id' => $concierge->id, 'name' => $concierge->name, 'created_at' => $concierge->created_at, 'subscription' => $subscription->makeVisible('notes'), 'usage' => $this->subscriptions->usage($concierge->id), 'limits' => $this->subscriptions->limits($subscription), 'features' => $this->subscriptions->features($subscription), 'monthly_price_cents' => $this->subscriptions->price($subscription), 'usable' => $this->subscriptions->usable($subscription), 'members' => Membership::where('concierge_id', $concierge->id)->with('user:id,name,email,is_admin,disabled_at')->get()];
        });

        return Inertia::render('administration/index', ['plans' => SubscriptionPlan::orderBy('monthly_price_cents')->get(), 'addons' => PlanAddon::orderBy('name')->get(), 'concierges' => $concierges, 'users' => User::select('id', 'name', 'email', 'is_admin', 'disabled_at', 'created_at')->orderByDesc('id')->get(), 'settings' => $settings, 'limitLabels' => SubscriptionManager::LIMITS, 'featureLabels' => SubscriptionManager::FEATURES, 'seoManagement' => app(SeoManager::class)->administration(), 'events' => DB::table('administration_events')->join('users', 'users.id', '=', 'administration_events.user_id')->select('administration_events.*', 'users.name as actor')->orderByDesc('administration_events.id')->limit(100)->get()]);
    }

    /** @return array<string,mixed> */
    private function catalogData(Request $request, bool $addon = false): array
    {
        $limitKey = $addon ? 'limit_bonus' : 'limits';
        $limits = array_keys(SubscriptionManager::LIMITS);
        $features = array_keys(SubscriptionManager::FEATURES);
        $rules = ['name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:3000'], 'monthly_price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999'], 'is_active' => ['required', 'boolean'], $limitKey => ['required', 'array:'.implode(',', $limits)], 'features' => ['required', 'array:'.implode(',', $features)]];
        if (! $addon) {
            $rules['is_public'] = ['required', 'boolean'];
        }
        foreach ($limits as $limit) {
            $rules[$limitKey.'.'.$limit] = [$addon ? 'required' : 'nullable', 'integer', 'min:0', 'max:1000000'];
        }
        foreach ($features as $feature) {
            $rules['features.'.$feature] = ['required', 'boolean'];
        }
        $data = $request->validate($rules);
        $data['monthly_price_cents'] = $this->subscriptions->cents((string) $data['monthly_price']);
        unset($data['monthly_price']);

        return $data;
    }

    public function storePlan(Request $request): RedirectResponse
    {
        $data = $this->catalogData($request);
        DB::transaction(function () use ($request, $data) {
            $plan = SubscriptionPlan::create($data);
            $this->log($request, 'Offre créée : '.$plan->name, ['plan_id' => $plan->id]);
        });

        return back()->with('success', 'Offre créée.');
    }

    public function updatePlan(Request $request, int $plan): RedirectResponse
    {
        $record = SubscriptionPlan::findOrFail($plan);
        $data = $this->catalogData($request);
        if (! $data['is_active'] && $this->subscriptions->settings()->default_plan_id === $record->id) {
            throw ValidationException::withMessages(['is_active' => 'Choisissez une autre offre par défaut avant de désactiver celle-ci.']);
        }
        DB::transaction(function () use ($request, $record, $data) {
            $before = $record->toArray();
            $record->update($data);
            $this->log($request, 'Offre modifiée : '.$record->name, ['before' => $before, 'after' => $record->toArray()]);
        });

        return back()->with('success', 'Offre mise à jour. Les conditions des abonnements existants sont conservées.');
    }

    public function storeAddon(Request $request): RedirectResponse
    {
        $data = $this->catalogData($request, true);
        DB::transaction(function () use ($request, $data) {
            $addon = PlanAddon::create($data);
            $this->log($request, 'Option créée : '.$addon->name, ['addon_id' => $addon->id]);
        });

        return back()->with('success', 'Option créée.');
    }

    public function updateAddon(Request $request, int $addon): RedirectResponse
    {
        $record = PlanAddon::findOrFail($addon);
        $data = $this->catalogData($request, true);
        DB::transaction(function () use ($request, $record, $data) {
            $before = $record->toArray();
            $record->update($data);
            $this->log($request, 'Option modifiée : '.$record->name, ['before' => $before, 'after' => $record->toArray()]);
        });

        return back()->with('success', 'Option mise à jour. Les abonnements existants gardent leurs conditions.');
    }

    public function subscription(Request $request, int $concierge): RedirectResponse
    {
        $record = Concierge::findOrFail($concierge);
        $rules = ['plan_id' => ['required', 'integer', 'exists:subscription_plans,id'], 'addon_ids' => ['present', 'array', 'max:30'], 'addon_ids.*' => ['integer', 'distinct', 'exists:plan_addons,id'], 'status' => ['required', Rule::in(['trialing', 'active', 'suspended', 'cancelled'])], 'starts_on' => ['required', 'date'], 'expires_at' => ['nullable', 'required_if:status,trialing', 'date', 'after:starts_on'], 'notes' => ['nullable', 'string', 'max:5000'], 'price_override' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999'], 'refresh_terms' => ['boolean'], 'limit_overrides' => ['present', 'array:'.implode(',', array_keys(SubscriptionManager::LIMITS))], 'feature_overrides' => ['present', 'array:'.implode(',', array_keys(SubscriptionManager::FEATURES))]];
        foreach (array_keys(SubscriptionManager::LIMITS) as $limit) {
            $rules['limit_overrides.'.$limit] = ['nullable', 'integer', 'min:0', 'max:1000000'];
        }
        foreach (array_keys(SubscriptionManager::FEATURES) as $feature) {
            $rules['feature_overrides.'.$feature] = ['boolean'];
        }
        $data = $request->validate($rules);
        DB::transaction(function () use ($request, $record, $data) {
            Concierge::whereKey($record->id)->lockForUpdate()->firstOrFail();
            $subscription = $this->subscriptions->ensure($record->id);
            $before = $subscription->toArray();
            $plan = SubscriptionPlan::whereKey($data['plan_id'])->firstOrFail();
            $addons = PlanAddon::whereIn('id', $data['addon_ids'])->get();
            $currentIds = array_column($subscription->terms['addons'], 'id');
            sort($currentIds);
            $newIds = array_map(fn ($id) => (int) $id, $data['addon_ids']);
            sort($newIds);
            $changed = $subscription->subscription_plan_id !== $plan->id || $currentIds !== $newIds || ($data['refresh_terms'] ?? false);
            if ($changed && (! $plan->is_active || $addons->contains(fn (PlanAddon $addon) => ! $addon->is_active))) {
                throw ValidationException::withMessages(['plan_id' => 'Seules les offres et options actives peuvent être attribuées ou réappliquées.']);
            }
            $subscription->update(['subscription_plan_id' => $plan->id, 'status' => $data['status'], 'terms' => $changed ? $this->subscriptions->terms($plan, $addons->all()) : $subscription->terms, 'limit_overrides' => $data['limit_overrides'], 'feature_overrides' => $data['feature_overrides'], 'price_override_cents' => isset($data['price_override']) ? $this->subscriptions->cents((string) $data['price_override']) : null, 'starts_on' => $data['starts_on'], 'expires_at' => isset($data['expires_at']) ? Carbon::parse($data['expires_at'], 'Europe/Paris')->utc() : null, 'notes' => $data['notes'] ?? null]);
            $this->log($request, 'Abonnement modifié : '.$record->name, ['before' => $before, 'after' => $subscription->toArray()], $record->id);
        });

        return back()->with('success', 'Abonnement mis à jour. Les nouvelles limites sont appliquées côté serveur.');
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate(['default_plan_id' => ['required', 'integer', Rule::exists('subscription_plans', 'id')->where('is_active', true)], 'trial_days' => ['required', 'integer', 'min:1', 'max:365'], 'registration_open' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $data) {
            $this->subscriptions->settings()->update($data);
            $this->log($request, 'Paramètres de l’application modifiés', $data);
        });

        return back()->with('success', 'Paramètres de l’application enregistrés.');
    }

    public function user(Request $request, int $user): RedirectResponse
    {
        $record = User::findOrFail($user);
        $data = $request->validate(['is_admin' => ['required', 'boolean'], 'disabled' => ['required', 'boolean']]);
        abort_if($record->id === $request->user()->id && ($data['disabled'] || ! $data['is_admin']), 422, 'Vous ne pouvez pas retirer votre propre accès administrateur.');
        DB::transaction(function () use ($request, $record, $data) {
            $record->forceFill(['is_admin' => $data['is_admin'], 'disabled_at' => $data['disabled'] ? now() : null])->save();
            $this->log($request, 'Accès utilisateur modifié : '.$record->email, $data);
        });

        return back()->with('success', 'Accès du compte mis à jour.');
    }

    public function visit(Request $request, int $concierge): RedirectResponse
    {
        Concierge::findOrFail($concierge);
        $request->session()->put('admin_concierge_id', $concierge);
        $this->log($request, 'Espace conciergerie ouvert en administration', [], $concierge);

        return to_route('dashboard');
    }

    public function leave(Request $request): RedirectResponse
    {
        $request->session()->forget('admin_concierge_id');

        return to_route('admin.index');
    }

    /** @param array<string,mixed> $details */
    private function log(Request $request, string $description, array $details = [], ?int $conciergeId = null): void
    {
        DB::table('administration_events')->insert(['user_id' => $request->user()->id, 'concierge_id' => $conciergeId, 'description' => $description, 'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
    }
}
