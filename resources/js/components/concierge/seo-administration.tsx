import { useForm } from '@inertiajs/react';
import {
    ArrowUpRight,
    Check,
    FileText,
    Globe,
    Plus,
    Search,
    Settings,
    ShieldCheck,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { BusinessForm, Field } from '@/components/concierge/business-form';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import * as seoRoutes from '@/routes/admin/seo';
import * as pageRoutes from '@/routes/admin/seo/pages';
import type { SeoContent, SeoManagement } from '@/types/seo';

export function SeoAdministration({ data }: { data: SeoManagement }) {
    const [tab, setTab] = useState('pages');
    const [editor, setEditor] = useState<SeoContent | 'new' | null>(null);
    const published = data.pages.filter((p) => p.status === 'published');
    const warnings = data.pages.reduce((n, p) => n + p.warnings.length, 0);
    const settings = data.settings;
    return (
        <>
            <div className="seo-summary">
                <article className="workspace-card">
                    <Globe size={20} />
                    <small>Adresse publique</small>
                    <strong>
                        {data.public_domain
                            ? data.effective_url
                            : 'Domaine à définir'}
                    </strong>
                    <span>
                        {data.effective_name} · {data.environment}
                    </span>
                </article>
                <article className="workspace-card">
                    <ShieldCheck size={20} />
                    <small>Indexation effective</small>
                    <strong>
                        {data.indexing_active ? 'Activée' : 'Désactivée'}
                    </strong>
                    <span>
                        {data.indexing_active
                            ? 'Pages publiées et autorisées uniquement'
                            : 'Le site reste exclu des moteurs'}
                    </span>
                </article>
                <article className="workspace-card">
                    <FileText size={20} />
                    <small>Pages publiques</small>
                    <strong>{published.length} publiées</strong>
                    <span>
                        {data.pages.length - published.length} brouillon(s) ·{' '}
                        {warnings} conseil(s)
                    </span>
                </article>
            </div>
            <nav className="seo-tabs" aria-label="Gestion du référencement">
                {[
                    {
                        key: 'pages',
                        label: 'Pages et contenus',
                        icon: FileText,
                    },
                    {
                        key: 'settings',
                        label: 'Identité et domaine',
                        icon: Settings,
                    },
                    {
                        key: 'launch',
                        label: 'Promotion et suivi',
                        icon: Search,
                    },
                ].map(({ key, label, icon: Icon }) => (
                    <button
                        key={key}
                        onClick={() => setTab(key)}
                        aria-current={tab === key ? 'page' : undefined}
                        className={tab === key ? 'active' : ''}
                    >
                        <Icon size={16} />
                        {label}
                    </button>
                ))}
            </nav>
            {tab === 'pages' && (
                <section className="workspace-card">
                    <div className="workspace-card-heading">
                        <div>
                            <h2>Vos pages de découverte</h2>
                            <p className="form-hint">
                                Contenus, aperçus Google et partage social. Les
                                pages principales sont également modifiables.
                            </p>
                        </div>
                        <button
                            className="brand-button small"
                            onClick={() => setEditor('new')}
                        >
                            <Plus size={15} />
                            Nouvelle page
                        </button>
                    </div>
                    <div className="seo-pages">
                        {data.pages.map((page) => (
                            <article key={page.id}>
                                <div>
                                    <span
                                        className={
                                            'status-chip ' +
                                            (page.status === 'published'
                                                ? 'green'
                                                : 'amber')
                                        }
                                    >
                                        {page.status === 'published'
                                            ? 'Publiée'
                                            : 'Brouillon'}
                                        {page.noindex ? ' · Non indexable' : ''}
                                    </span>
                                    <h3>{page.heading}</h3>
                                    <code>{page.path}</code>
                                    <p>{page.resolved_description}</p>
                                    {page.warnings.map((warning, i) => (
                                        <small className="seo-warning" key={i}>
                                            {warning}
                                        </small>
                                    ))}
                                </div>
                                <div className="seo-page-actions">
                                    <button
                                        className="brand-button secondary small"
                                        onClick={() => setEditor(page)}
                                    >
                                        Modifier
                                    </button>
                                    {page.status === 'published' && (
                                        <a
                                            href={page.path}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="terrain-link"
                                        >
                                            Voir <ArrowUpRight size={14} />
                                        </a>
                                    )}
                                </div>
                            </article>
                        ))}
                    </div>
                </section>
            )}
            {tab === 'settings' && (
                <section className="workspace-card seo-settings">
                    <h2>Une identité et une adresse configurables</h2>
                    <p className="form-hint">
                        Les champs vides utilisent APP_NAME et APP_URL du
                        fichier .env. Aucun domaine définitif n’est nécessaire
                        aujourd’hui.
                    </p>
                    <BusinessForm
                        key={JSON.stringify(settings)}
                        action={seoRoutes.settings.url()}
                        transform={(values) => ({
                            ...values,
                            indexing_enabled: 'indexing_enabled' in values,
                            allow_ai_search: 'allow_ai_search' in values,
                            allow_ai_training: 'allow_ai_training' in values,
                        })}
                    >
                        <div className="admin-feature-grid">
                            <Field
                                label="Nom public de l’application"
                                name="site_name"
                                defaultValue={settings.site_name ?? ''}
                                placeholder={data.effective_name}
                            />
                            <Field
                                label="Domaine public HTTPS"
                                name="public_url"
                                type="url"
                                defaultValue={settings.public_url ?? ''}
                                placeholder="https://votre-domaine.fr"
                            />
                        </div>
                        <p className="form-hint">
                            Adresse utilisée pour les liens canoniques, le
                            sitemap et les données structurées. Laissez vide
                            tant que votre domaine n’est pas choisi.
                        </p>
                        <label className="admin-checkbox">
                            <input
                                type="checkbox"
                                name="indexing_enabled"
                                value="1"
                                defaultChecked={settings.indexing_enabled}
                            />
                            Autoriser l’indexation du site public
                        </label>
                        <p className="form-hint">
                            Cette autorisation ne prend effet qu’en production
                            avec un domaine public. Les adresses .test, locales
                            et privées restent bloquées.
                        </p>
                        <label className="admin-checkbox">
                            <input
                                type="checkbox"
                                name="allow_ai_search"
                                value="1"
                                defaultChecked={settings.allow_ai_search}
                            />
                            Autoriser OAI-SearchBot pour la recherche dans
                            ChatGPT
                        </label>
                        <label className="admin-checkbox">
                            <input
                                type="checkbox"
                                name="allow_ai_training"
                                value="1"
                                defaultChecked={settings.allow_ai_training}
                            />
                            Autoriser GPTBot pour l’entraînement des modèles
                        </label>
                        <p className="form-hint">
                            Ces autorisations sont indépendantes. Seules les
                            pages publiques sont concernées ; les espaces métier
                            sont exclus.
                        </p>
                        <div className="admin-feature-grid">
                            <Field
                                label="Jeton de vérification Google Search Console"
                                name="google_verification"
                                defaultValue={
                                    settings.google_verification ?? ''
                                }
                                placeholder="Valeur content du jeton HTML"
                            />
                            <Field
                                label="Jeton de vérification Bing Webmaster Tools"
                                name="bing_verification"
                                defaultValue={settings.bing_verification ?? ''}
                                placeholder="Valeur content du jeton HTML"
                            />
                        </div>
                        <Field
                            label="Image de partage social (URL HTTPS)"
                            name="social_image"
                            type="url"
                            defaultValue={settings.social_image ?? ''}
                            placeholder="https://votre-domaine.fr/image-partage.jpg"
                        />
                        <Field
                            label="Adresse de contact publique (facultative)"
                            name="contact_email"
                            type="email"
                            defaultValue={settings.contact_email ?? ''}
                        />
                    </BusinessForm>
                </section>
            )}
            {tab === 'launch' && (
                <div className="seo-launch-grid">
                    <section className="workspace-card">
                        <span className="section-kicker">
                            Préparer la mise en ligne
                        </span>
                        <h2>Du contenu à la découverte de l’application</h2>
                        <ul className="seo-checklist">
                            <li data-done={data.public_domain}>
                                <Check size={17} />
                                <div>
                                    <b>
                                        Choisir et configurer le domaine public
                                    </b>
                                    <p>
                                        Ajoutez-le ici ou dans APP_URL. Le nom
                                        affiché vient de APP_NAME si aucun nom
                                        public n’est renseigné.
                                    </p>
                                </div>
                            </li>
                            <li data-done={data.indexing_active}>
                                <Check size={17} />
                                <div>
                                    <b>
                                        Mettre le site en production et
                                        autoriser l’indexation
                                    </b>
                                    <p>
                                        Le certificat HTTPS et
                                        APP_ENV=production sont nécessaires. Le
                                        statut effectif est affiché au-dessus.
                                    </p>
                                </div>
                            </li>
                            <li
                                data-done={Boolean(
                                    settings.google_verification &&
                                    settings.bing_verification,
                                )}
                            >
                                <Check size={17} />
                                <div>
                                    <b>Vérifier vos comptes Google et Bing</b>
                                    <p>
                                        Ajoutez les jetons HTML, validez votre
                                        propriété dans chaque service puis
                                        soumettez le sitemap.
                                    </p>
                                </div>
                            </li>
                            <li data-done={warnings === 0}>
                                <Check size={17} />
                                <div>
                                    <b>Relire les pages et les aperçus</b>
                                    <p>
                                        Des titres distincts, des réponses
                                        concrètes et des fonctionnalités
                                        décrites avec précision.
                                    </p>
                                </div>
                            </li>
                            <li data-done={Boolean(settings.social_image)}>
                                <Check size={17} />
                                <div>
                                    <b>Préparer vos partages</b>
                                    <p>
                                        Ajoutez une image et partagez les pages
                                        pertinentes dans vos réseaux
                                        professionnels et auprès de vos
                                        partenaires.
                                    </p>
                                </div>
                            </li>
                        </ul>
                    </section>
                    <section className="workspace-card">
                        <span className="section-kicker">Outils utiles</span>
                        <h2>Mesurer les résultats réels</h2>
                        <p>
                            Les impressions, clics et requêtes se consultent
                            dans Search Console. Bing propose également le suivi
                            des citations dans ses expériences IA. Ces comptes
                            ne sont pas encore connectés.
                        </p>
                        <div className="seo-tools">
                            <a
                                href="/sitemap.xml"
                                target="_blank"
                                rel="noreferrer"
                            >
                                Sitemap XML <ArrowUpRight size={15} />
                            </a>
                            <a
                                href="/robots.txt"
                                target="_blank"
                                rel="noreferrer"
                            >
                                Règles robots.txt <ArrowUpRight size={15} />
                            </a>
                            <a
                                href="/llms.txt"
                                target="_blank"
                                rel="noreferrer"
                            >
                                Résumé des pages publiques{' '}
                                <ArrowUpRight size={15} />
                            </a>
                            <a
                                href="https://search.google.com/search-console"
                                target="_blank"
                                rel="noreferrer"
                            >
                                Google Search Console <ArrowUpRight size={15} />
                            </a>
                            <a
                                href="https://www.bing.com/webmasters/"
                                target="_blank"
                                rel="noreferrer"
                            >
                                Bing Webmaster Tools <ArrowUpRight size={15} />
                            </a>
                        </div>
                        <p className="form-hint">
                            Le contenu, les liens internes et les données
                            structurées sont générés à partir des pages
                            publiées. Le fichier llms.txt reste un résumé
                            facultatif ; il ne garantit aucune visibilité.
                        </p>
                    </section>
                </div>
            )}
            <Dialog
                open={editor !== null}
                onOpenChange={(open) => !open && setEditor(null)}
            >
                <DialogContent className="seo-editor-dialog">
                    <DialogHeader>
                        <DialogTitle>
                            {editor === 'new'
                                ? 'Créer une page publique'
                                : 'Modifier la page publique'}
                        </DialogTitle>
                        <DialogDescription>
                            Le contenu enregistré est utilisé sur le site, dans
                            les métadonnées et les données structurées. Le
                            marqueur {'{app_name}'} suit automatiquement le nom
                            de l’application.
                        </DialogDescription>
                    </DialogHeader>
                    {editor && (
                        <PageEditor
                            key={editor === 'new' ? 'new' : editor.id}
                            page={editor === 'new' ? null : editor}
                            name={data.effective_name}
                            baseUrl={data.effective_url}
                            onSaved={() => setEditor(null)}
                        />
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

type PageData = {
    path: string;
    title: string;
    description: string;
    heading: string;
    intro: string;
    keyword: string;
    social_title: string;
    social_description: string;
    status: 'draft' | 'published';
    noindex: boolean;
    sections: { heading: string; text: string }[];
    faq: { question: string; answer: string }[];
};
function PageEditor({
    page,
    name,
    baseUrl,
    onSaved,
}: {
    page: SeoContent | null;
    name: string;
    baseUrl: string;
    onSaved: () => void;
}) {
    const form = useForm<PageData>({
        path: page?.path ?? '/guides/',
        title: page?.title ?? '',
        description: page?.description ?? '',
        heading: page?.heading ?? '',
        intro: page?.intro ?? '',
        keyword: page?.keyword ?? '',
        social_title: page?.social_title ?? '',
        social_description: page?.social_description ?? '',
        status: page?.status ?? 'draft',
        noindex: page?.noindex ?? false,
        sections: page?.sections ?? [{ heading: '', text: '' }],
        faq: page?.faq ?? [],
    });
    const resolve = (text: string) => text.replaceAll('{app_name}', name);
    const field = (
        key:
            | 'path'
            | 'title'
            | 'description'
            | 'heading'
            | 'intro'
            | 'keyword'
            | 'social_title'
            | 'social_description',
        label: string,
        multiline = false,
    ) => (
        <label className="form-field">
            {label}
            {multiline ? (
                <textarea
                    value={form.data[key]}
                    onChange={(e) => form.setData(key, e.target.value)}
                    required={['description', 'intro'].includes(key)}
                />
            ) : (
                <input
                    value={form.data[key]}
                    onChange={(e) => form.setData(key, e.target.value)}
                    required={['path', 'title', 'heading'].includes(key)}
                />
            )}
        </label>
    );
    return (
        <form
            className="business-form seo-editor"
            onSubmit={(e) => {
                e.preventDefault();
                form.post(
                    page
                        ? pageRoutes.update.url(page.id)
                        : pageRoutes.store.url(),
                    { preserveScroll: true, onSuccess: onSaved },
                );
            }}
        >
            {!page ? (
                <>
                    {field('path', 'Adresse de la page')}
                    <p className="form-hint">
                        Exemple : /guides/preparer-un-menage. Préfixes autorisés
                        : guides, fonctionnalites ou solutions.
                    </p>
                </>
            ) : (
                <p className="form-hint">Adresse conservée : {page.path}</p>
            )}
            <div className="seo-search-preview">
                <small>Aperçu dans les résultats de recherche</small>
                <span>
                    {baseUrl + (form.data.path === '/' ? '' : form.data.path)}
                </span>
                <b>{resolve(form.data.title) || 'Titre de votre page'}</b>
                <p>
                    {resolve(form.data.description) ||
                        'Une description précise qui donne envie de découvrir votre page.'}
                </p>
            </div>
            {field('title', 'Titre SEO')}
            {field('description', 'Description SEO', true)}
            <p className="form-hint">
                {resolve(form.data.title).length} caractères pour le titre ·{' '}
                {resolve(form.data.description).length} pour la description. Les
                aperçus des moteurs peuvent varier.
            </p>
            {field('keyword', 'Sujet ou expression ciblée (repère éditorial)')}
            {field('heading', 'Titre principal visible (H1)')}
            {field('intro', 'Introduction visible', true)}
            <div className="workspace-card-heading">
                <h3>Sections de contenu</h3>
                <button
                    type="button"
                    className="terrain-link"
                    onClick={() =>
                        form.setData('sections', [
                            ...form.data.sections,
                            { heading: '', text: '' },
                        ])
                    }
                >
                    <Plus size={14} />
                    Ajouter une section
                </button>
            </div>
            {form.data.sections.map((section, i) => (
                <fieldset className="seo-content-block" key={i}>
                    <legend>Section {i + 1}</legend>
                    <label className="form-field">
                        Sous-titre (H2)
                        <input
                            value={section.heading}
                            required
                            onChange={(e) =>
                                form.setData(
                                    'sections',
                                    form.data.sections.map((s, n) =>
                                        n === i
                                            ? { ...s, heading: e.target.value }
                                            : s,
                                    ),
                                )
                            }
                        />
                    </label>
                    <label className="form-field">
                        Texte
                        <textarea
                            required
                            value={section.text}
                            onChange={(e) =>
                                form.setData(
                                    'sections',
                                    form.data.sections.map((s, n) =>
                                        n === i
                                            ? { ...s, text: e.target.value }
                                            : s,
                                    ),
                                )
                            }
                        />
                    </label>
                    <button
                        type="button"
                        className="seo-remove"
                        onClick={() =>
                            form.setData(
                                'sections',
                                form.data.sections.filter((_, n) => n !== i),
                            )
                        }
                    >
                        <Trash2 size={14} />
                        Retirer cette section
                    </button>
                </fieldset>
            ))}
            <div className="workspace-card-heading">
                <h3>Questions et réponses</h3>
                <button
                    type="button"
                    className="terrain-link"
                    onClick={() =>
                        form.setData('faq', [
                            ...form.data.faq,
                            { question: '', answer: '' },
                        ])
                    }
                >
                    <Plus size={14} />
                    Ajouter une question
                </button>
            </div>
            {form.data.faq.map((item, i) => (
                <fieldset className="seo-content-block" key={i}>
                    <legend>Question {i + 1}</legend>
                    <label className="form-field">
                        Question
                        <input
                            value={item.question}
                            required
                            onChange={(e) =>
                                form.setData(
                                    'faq',
                                    form.data.faq.map((s, n) =>
                                        n === i
                                            ? { ...s, question: e.target.value }
                                            : s,
                                    ),
                                )
                            }
                        />
                    </label>
                    <label className="form-field">
                        Réponse
                        <textarea
                            value={item.answer}
                            required
                            onChange={(e) =>
                                form.setData(
                                    'faq',
                                    form.data.faq.map((s, n) =>
                                        n === i
                                            ? { ...s, answer: e.target.value }
                                            : s,
                                    ),
                                )
                            }
                        />
                    </label>
                    <button
                        type="button"
                        className="seo-remove"
                        onClick={() =>
                            form.setData(
                                'faq',
                                form.data.faq.filter((_, n) => n !== i),
                            )
                        }
                    >
                        <Trash2 size={14} />
                        Retirer cette question
                    </button>
                </fieldset>
            ))}
            <h3>Partage sur les réseaux</h3>
            {field('social_title', 'Titre de partage (facultatif)')}
            {field(
                'social_description',
                'Description de partage (facultative)',
                true,
            )}
            <label className="form-field">
                Publication
                <select
                    value={form.data.status}
                    onChange={(e) =>
                        form.setData(
                            'status',
                            e.target.value as PageData['status'],
                        )
                    }
                >
                    <option
                        value="draft"
                        disabled={Boolean(page && page.kind !== 'content')}
                    >
                        Brouillon
                    </option>
                    <option value="published">Publiée</option>
                </select>
            </label>
            <label className="admin-checkbox">
                <input
                    type="checkbox"
                    checked={form.data.noindex}
                    onChange={(e) => form.setData('noindex', e.target.checked)}
                />
                Exclure cette page de l’indexation
            </label>
            {Object.keys(form.errors).length > 0 && (
                <div className="form-errors" role="alert">
                    {Object.entries(form.errors).map(([key, message]) => (
                        <p key={key}>{message}</p>
                    ))}
                </div>
            )}
            <div className="business-form-footer">
                <button
                    className="brand-button small"
                    disabled={form.processing}
                    type="submit"
                >
                    {form.processing
                        ? 'Enregistrement…'
                        : 'Enregistrer la page'}
                </button>
            </div>
        </form>
    );
}
