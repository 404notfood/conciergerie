import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { Brand } from '@/components/concierge/brand';
import { MarketingBody } from '@/components/concierge/marketing-body';
import { SeoHead } from '@/components/concierge/seo-head';
import { home, login, register, pricing, resources } from '@/routes';
import type { MarketingProps } from '@/types/seo';
export default function ContentPage(props: MarketingProps) {
    const { content, seo } = props;
    return (
        <div className="brand-surface landing marketing-page">
            <SeoHead seo={seo} />
            <a className="skip-link" href="#main">
                Aller au contenu
            </a>
            <header className="landing-header">
                <div className="landing-container flex items-center justify-between gap-5">
                    <Link href={home()}>
                        <Brand name={seo.site_name} />
                    </Link>
                    <nav
                        className="marketing-nav"
                        aria-label="Navigation principale"
                    >
                        <Link href={resources()}>Ressources</Link>
                        <Link href={pricing()}>Tarifs</Link>
                        <Link href={login()}>Connexion</Link>
                    </nav>
                </div>
            </header>
            <main id="main">
                <section className="marketing-hero landing-container">
                    <nav aria-label="Fil d’Ariane">
                        <Link href={home()}>Accueil</Link> /{' '}
                        <Link href={resources()}>Ressources</Link>
                        {content.kind !== 'hub' && <> / {content.heading}</>}
                    </nav>
                    <span className="section-kicker">
                        {content.path.startsWith('/guides')
                            ? 'Guide pratique'
                            : content.path.startsWith('/solutions')
                              ? 'Votre activité'
                              : 'La plateforme'}
                    </span>
                    <h1>{content.heading}</h1>
                    <p>{content.intro}</p>
                    <Link href={register()} className="brand-button">
                        Créer mon espace <ArrowRight size={16} />
                    </Link>
                </section>
                <MarketingBody {...props} />
            </main>
            <footer className="landing-footer">
                <div className="landing-container">
                    <Link href={home()}>
                        <Brand name={seo.site_name} compact />
                    </Link>
                    <p>La plateforme de gestion des conciergeries</p>
                    <Link href={pricing()}>Découvrir les offres</Link>
                </div>
            </footer>
        </div>
    );
}
