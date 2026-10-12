import { MarketingBody } from '@/components/concierge/marketing-body';
import { SeoHead } from '@/components/concierge/seo-head';
import type { MarketingProps } from '@/types/seo';
import { Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Building2,
    CalendarCheck2,
    CalendarDays,
    Camera,
    Check,
    CheckCheck,
    ClipboardCheck,
    Download,
    FileCheck2,
    House,
    Menu,
    RefreshCw,
    Send,
    Users,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { Brand } from '@/components/concierge/brand';
import {
    PhonePreview,
    ProductPreview,
} from '@/components/concierge/product-preview';
import { dashboard, login, register, pricing } from '@/routes';
const steps = [
    {
        icon: CalendarCheck2,
        title: 'Planifier',
        text: 'Créez vos missions et organisez vos journées.',
    },
    {
        icon: Users,
        title: 'Déléguer',
        text: 'Attribuez chaque intervention à la bonne personne.',
    },
    {
        icon: Check,
        title: 'Réaliser',
        text: 'Vos équipes suivent leurs missions sur le terrain.',
    },
    {
        icon: FileCheck2,
        title: 'Contrôler',
        text: 'Vérifiez les comptes rendus et les preuves photo.',
    },
    {
        icon: Send,
        title: 'Informer',
        text: 'Partagez un suivi clair avec chacun de vos clients.',
    },
];
export default function Welcome(props: MarketingProps) {
    const { content, seo } = props;
    const { auth } = usePage().props;
    const [menuOpen, setMenuOpen] = useState(false);
    const start = auth.user ? dashboard() : register();
    return (
        <div className="brand-surface landing">
            <SeoHead seo={seo} />
            <a className="skip-link" href="#main">
                Aller au contenu
            </a>
            <header className="landing-header">
                <div className="landing-container flex items-center justify-between gap-6">
                    <Link href="/" aria-label={seo.site_name + ', accueil'}>
                        <Brand name={seo.site_name} />
                    </Link>
                    <nav
                        className="desktop-nav"
                        aria-label="Navigation principale"
                    >
                        <a href="#fonctionnalites">Fonctionnalités</a>
                        <a href="#terrain">Sur le terrain</a>
                        <Link href={pricing()}>Tarifs</Link>
                        <Link href="/ressources">Ressources</Link>
                    </nav>
                    <div className="header-actions">
                        <Link href={auth.user ? dashboard() : login()}>
                            {auth.user ? 'Mon tableau de bord' : 'Connexion'}
                        </Link>
                        <Link className="brand-button small" href={start}>
                            {auth.user ? 'Mon espace' : 'Créer mon espace'}
                        </Link>
                    </div>
                    <button
                        className="mobile-menu-button"
                        aria-label={
                            menuOpen ? 'Fermer le menu' : 'Ouvrir le menu'
                        }
                        aria-expanded={menuOpen}
                        onClick={() => setMenuOpen(!menuOpen)}
                    >
                        {menuOpen ? <X /> : <Menu />}
                    </button>
                </div>
                {menuOpen ? (
                    <nav className="mobile-nav">
                        <a
                            href="#fonctionnalites"
                            onClick={() => setMenuOpen(false)}
                        >
                            Fonctionnalités
                        </a>
                        <a href="#terrain" onClick={() => setMenuOpen(false)}>
                            Sur le terrain
                        </a>
                        <Link href={login()}>Connexion</Link>
                        <Link href={start}>Créer mon espace</Link>
                    </nav>
                ) : null}
            </header>
            <main id="main">
                <section className="landing-hero">
                    <div className="hero-ambient" />
                    <div className="landing-container hero-grid">
                        <div className="hero-copy">
                            <span className="eyebrow">
                                La gestion de votre conciergerie
                            </span>
                            <h1>
                                {content.heading ===
                                'Toute votre conciergerie, organisée au même endroit.' ? (
                                    <>
                                        Toute votre
                                        <br />
                                        conciergerie,
                                        <br />
                                        organisée au
                                        <br />
                                        <span>même endroit.</span>
                                    </>
                                ) : (
                                    content.heading
                                )}
                            </h1>
                            <p>{content.intro}</p>
                            <div className="hero-actions">
                                <Link className="brand-button" href={start}>
                                    Créer mon espace <ArrowRight size={16} />
                                </Link>
                                <a
                                    className="brand-button secondary"
                                    href="#fonctionnalites"
                                >
                                    Découvrir la plateforme
                                </a>
                            </div>
                            <div className="hero-benefits">
                                <span>
                                    <CalendarDays />
                                    Planning
                                </span>
                                <span>
                                    <Users />
                                    Équipes
                                </span>
                                <span>
                                    <Camera />
                                    Preuves photo
                                </span>
                            </div>
                        </div>
                        <div className="hero-preview">
                            <ProductPreview name={seo.site_name} />
                            <div className="preview-floating">
                                <span>
                                    <CheckCheck size={17} />
                                </span>
                                <div>
                                    <b>Chaque intervention, bien suivie.</b>
                                    <small>
                                        Du planning au compte rendu validé.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
                <section id="fonctionnalites" className="workflow-section">
                    <div className="landing-container">
                        <div className="section-heading">
                            <span className="section-kicker">
                                Une journée plus simple
                            </span>
                            <h2>
                                Du planning au compte rendu, tout est relié.
                            </h2>
                            <p>
                                Un même suivi pour vos équipes, vos prestataires
                                et vos clients.
                            </p>
                        </div>
                        <div className="workflow-grid">
                            {steps.map(({ icon: Icon, title, text }, i) => (
                                <article key={title} className="workflow-step">
                                    <div className="step-icon">
                                        <Icon size={28} />
                                        <span>
                                            {String(i + 1).padStart(2, '0')}
                                        </span>
                                    </div>
                                    <h3>{title}</h3>
                                    <p>{text}</p>
                                </article>
                            ))}
                        </div>
                    </div>
                </section>
                <section id="terrain" className="terrain-section">
                    <div className="landing-container terrain-grid">
                        <div className="phone-wrap">
                            <div className="phone-orbit" />
                            <PhonePreview />
                        </div>
                        <div className="terrain-copy">
                            <span className="section-kicker">
                                Pensée pour vos équipes
                            </span>
                            <h2>
                                Sur le terrain,
                                <br />
                                même sans réseau.
                            </h2>
                            <p className="section-intro">
                                Préparez votre journée, conservez vos
                                observations et vos photos, puis synchronisez au
                                retour.
                            </p>
                            <div className="terrain-features">
                                {[
                                    {
                                        icon: Download,
                                        title: 'Missions préparées',
                                        text: 'Emportez les consignes et les checklists de vos missions acceptées.',
                                    },
                                    {
                                        icon: Camera,
                                        title: 'Brouillons et photos',
                                        text: 'Enregistrez votre travail sur l’appareil, même sans connexion.',
                                    },
                                    {
                                        icon: RefreshCw,
                                        title: 'Reprise des envois',
                                        text: 'Retrouvez les éléments en attente et leur état de réception.',
                                    },
                                ].map(({ icon: Icon, title, text }) => (
                                    <div key={title}>
                                        <span>
                                            <Icon size={25} />
                                        </span>
                                        <div>
                                            <h3>{title}</h3>
                                            <p>{text}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                            <a className="terrain-link" href={login().url}>
                                Accéder à mon espace terrain{' '}
                                <ArrowRight size={16} />
                            </a>
                        </div>
                    </div>
                </section>
                <section className="activities-section">
                    <div className="landing-container">
                        <div className="section-heading">
                            <h2>
                                Une plateforme qui s’adapte à votre activité.
                            </h2>
                            <p>
                                Votre métier change. Votre organisation reste
                                claire.
                            </p>
                        </div>
                        <div className="activities-grid">
                            {[
                                {
                                    icon: House,
                                    title: 'Location saisonnière',
                                    text: 'Suivez les séjours, les ménages et les constats des biens.',
                                },
                                {
                                    icon: Building2,
                                    title: 'Intendance de résidences',
                                    text: 'Organisez l’entretien régulier et les interventions techniques.',
                                },
                                {
                                    icon: Users,
                                    title: 'Services de conciergerie',
                                    text: 'Coordonnez vos équipes pour vos services aux particuliers et entreprises.',
                                },
                            ].map(({ icon: Icon, title, text }) => (
                                <article key={title}>
                                    <span>
                                        <Icon size={29} />
                                    </span>
                                    <div>
                                        <h3>{title}</h3>
                                        <p>{text}</p>
                                    </div>
                                </article>
                            ))}
                        </div>
                    </div>
                </section>
                <section className="landing-cta">
                    <div className="cta-mark">
                        <House />
                    </div>
                    <div className="landing-container">
                        <span>
                            Tout commence par une journée bien organisée.
                        </span>
                        <h2>Organisez votre prochaine journée.</h2>
                        <Link className="brand-button" href={start}>
                            Créer mon espace <ArrowRight size={17} />
                        </Link>
                        <p>
                            <ClipboardCheck size={14} /> Votre conciergerie. Vos
                            équipes. Un seul espace.
                        </p>
                    </div>
                </section>
                <MarketingBody {...props} />
            </main>
            <footer className="landing-footer">
                <div className="landing-container">
                    <Brand compact name={seo.site_name} />
                    <p>La plateforme de gestion des conciergeries</p>
                    <nav>
                        <Link href="/ressources">
                            Guides et fonctionnalités
                        </Link>
                        <a href="#fonctionnalites">Fonctionnalités</a>
                        <a href="#terrain">Sur le terrain</a>
                    </nav>
                </div>
            </footer>
        </div>
    );
}
