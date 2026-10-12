import { MarketingBody } from '@/components/concierge/marketing-body';
import { SeoHead } from '@/components/concierge/seo-head';
import type { MarketingProps } from '@/types/seo';
import { Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { Brand } from '@/components/concierge/brand';
import { home, register, login } from '@/routes';
import type { CatalogItem } from '@/types/subscription';
import { euros } from '@/types/subscription';
export default function Pricing({
    content,
    seo,
    publicLinks,
    plans,
    limitLabels,
    featureLabels,
}: MarketingProps & {
    plans: CatalogItem[];
    limitLabels: Record<string, string>;
    featureLabels: Record<string, string>;
}) {
    return (
        <div className="brand-surface subscription-page">
            <SeoHead seo={seo} />
            <header className="landing-header">
                <div className="landing-container flex items-center justify-between">
                    <Link href={home()}>
                        <Brand name={seo.site_name} />
                    </Link>
                    <Link href={login()} className="text-xs">
                        Connexion
                    </Link>
                </div>
            </header>
            <main className="subscription-content">
                <div className="section-heading mb-8">
                    <span className="section-kicker">
                        Votre organisation, à votre mesure
                    </span>
                    <h1 className="text-4xl font-semibold tracking-tight">
                        {content.heading}
                    </h1>
                    <p>{content.intro}</p>
                </div>
                {plans.length ? (
                    <div className="directory-grid">
                        {plans.map((plan) => (
                            <article
                                className="workspace-card admin-plan-card"
                                key={plan.id}
                            >
                                <h2>{plan.name}</h2>
                                <p>{plan.description}</p>
                                <div className="admin-plan-price">
                                    {euros(plan.monthly_price_cents)}
                                    <small>/ mois</small>
                                </div>
                                <div className="admin-plan-limits">
                                    {Object.entries(plan.limits ?? {}).map(
                                        ([key, value]) => (
                                            <div key={key}>
                                                <span>{limitLabels[key]}</span>
                                                <b>
                                                    {value === null
                                                        ? 'Illimité'
                                                        : value}
                                                </b>
                                            </div>
                                        ),
                                    )}
                                </div>
                                <div className="admin-plan-options">
                                    {Object.entries(featureLabels)
                                        .filter(([key]) => plan.features[key])
                                        .map(([key, label]) => (
                                            <span key={key}>
                                                <Check size={12} />
                                                {label}
                                            </span>
                                        ))}
                                </div>
                                <Link
                                    href={register()}
                                    className="brand-button small mt-5"
                                >
                                    Créer mon espace
                                </Link>
                            </article>
                        ))}
                    </div>
                ) : (
                    <section className="workspace-card admin-empty">
                        <h2 className="mb-3 text-lg font-semibold text-navy">
                            Les offres seront présentées ici.
                        </h2>
                        <p>
                            Vous pouvez déjà créer votre espace pour découvrir
                            la plateforme.
                        </p>
                        <Link
                            className="brand-button small mt-5"
                            href={register()}
                        >
                            Créer mon espace
                        </Link>
                    </section>
                )}
                <Link
                    className="mt-8 block text-center text-xs text-teal"
                    href={home()}
                >
                    Retour à l’accueil
                </Link>
                <MarketingBody content={content} publicLinks={publicLinks} />
            </main>
        </div>
    );
}
