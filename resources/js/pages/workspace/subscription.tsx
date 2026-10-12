import { Head, Link } from '@inertiajs/react';
import { Check, CreditCard, ShieldCheck, TriangleAlert } from 'lucide-react';
import { InertiaBrand as Brand } from '@/components/concierge/brand';
import { dashboard, logout } from '@/routes';
import type { Features, Limits, Subscription } from '@/types/subscription';
import { euros } from '@/types/subscription';
export default function SubscriptionPage({
    subscription,
    limits,
    features,
    usage,
    monthly_price_cents,
    limitLabels,
    featureLabels,
    blocked,
}: {
    subscription: Subscription;
    limits: Limits;
    features: Features;
    usage: Record<string, number>;
    monthly_price_cents: number | null;
    limitLabels: Record<string, string>;
    featureLabels: Record<string, string>;
    blocked: boolean;
}) {
    return (
        <div className="brand-surface subscription-page">
            <Head title="Mon abonnement" />
            <header className="landing-header">
                <div className="landing-container flex items-center justify-between">
                    <Brand />
                    <Link href={logout()} as="button" className="text-xs">
                        Me déconnecter
                    </Link>
                </div>
            </header>
            <main className="subscription-content">
                <span className="section-kicker">Votre conciergerie</span>
                <h1>Mon abonnement</h1>
                <p className="form-hint">
                    Votre offre, vos options et vos capacités au même endroit.
                </p>
                {blocked ? (
                    <div className="form-errors mt-6">
                        <TriangleAlert size={18} className="mb-2" />
                        Votre accès est suspendu ou arrivé à échéance. Contactez
                        l’administrateur pour renouveler ou adapter votre
                        abonnement. Les données enregistrées et les preuves
                        locales sont conservées.
                    </div>
                ) : null}
                <section className="workspace-card subscription-plan">
                    <div>
                        <span className="subscription-icon">
                            <CreditCard />
                        </span>
                        <h2>{subscription.terms.plan_name}</h2>
                        <p>
                            {
                                {
                                    trialing: 'Période d’essai',
                                    active: 'Abonnement actif',
                                    suspended: 'Accès suspendu',
                                    cancelled: 'Abonnement résilié',
                                }[subscription.status]
                            }
                        </p>
                        {subscription.expires_at ? (
                            <small>
                                Échéance :{' '}
                                {new Date(
                                    subscription.expires_at,
                                ).toLocaleDateString('fr-FR')}
                            </small>
                        ) : (
                            <small>Sans échéance renseignée</small>
                        )}
                    </div>
                    {monthly_price_cents !== null ? (
                        <div className="subscription-price">
                            {euros(monthly_price_cents)}
                            <small>/ mois</small>
                        </div>
                    ) : null}
                </section>
                <div className="subscription-usage-grid">
                    {Object.entries(limitLabels).map(([key, label]) => (
                        <article
                            className="workspace-card subscription-usage"
                            key={key}
                        >
                            <h3>{label}</h3>
                            <b>
                                {usage[key] ?? 0}
                                <span>
                                    {' '}
                                    /{' '}
                                    {limits[key] === null
                                        ? 'Illimité'
                                        : limits[key]}
                                </span>
                            </b>
                            {limits[key] !== null ? (
                                <div className="quota-track">
                                    <span
                                        className={
                                            (usage[key] ?? 0) >=
                                            Number(limits[key])
                                                ? 'full'
                                                : ''
                                        }
                                        style={{
                                            width:
                                                Math.min(
                                                    100,
                                                    ((usage[key] ?? 0) /
                                                        Math.max(
                                                            1,
                                                            Number(limits[key]),
                                                        )) *
                                                        100,
                                                ) + '%',
                                        }}
                                    />
                                </div>
                            ) : null}
                        </article>
                    ))}
                </div>
                <section className="workspace-card subscription-features">
                    <h2>
                        <ShieldCheck size={19} /> Options de votre abonnement
                    </h2>
                    {Object.entries(featureLabels).map(([key, label]) => (
                        <div
                            key={key}
                            className={!features[key] ? 'unavailable' : ''}
                        >
                            <Check size={16} />
                            {label}
                            <span>
                                {features[key] ? 'Incluse' : 'Non incluse'}
                            </span>
                        </div>
                    ))}
                    {subscription.terms.addons.length ? (
                        <p className="form-hint mt-5">
                            Options supplémentaires :{' '}
                            {subscription.terms.addons
                                .map((addon) => addon.name)
                                .join(', ')}
                        </p>
                    ) : null}
                </section>
                {!blocked ? (
                    <Link className="brand-button small" href={dashboard()}>
                        Retour à mon espace
                    </Link>
                ) : null}
            </main>
        </div>
    );
}
