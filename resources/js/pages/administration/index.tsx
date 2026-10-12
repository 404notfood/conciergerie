import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    Activity,
    ArrowRight,
    Building2,
    Check,
    CreditCard,
    FileClock,
    LayoutDashboard,
    LogOut,
    Plus,
    Search,
    Settings,
    ShieldCheck,
    SlidersHorizontal,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import { InertiaBrand as Brand } from '@/components/concierge/brand';
import { SeoAdministration } from '@/components/concierge/seo-administration';
import {
    BusinessForm,
    Field,
    SelectField,
    TextArea,
} from '@/components/concierge/business-form';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { logout } from '@/routes';
import * as adminRoutes from '@/routes/admin';
import * as planRoutes from '@/routes/admin/plans';
import * as addonRoutes from '@/routes/admin/addons';
import * as subscriptionRoutes from '@/routes/admin/subscriptions';
import * as conciergeRoutes from '@/routes/admin/concierges';
import * as userRoutes from '@/routes/admin/users';
import * as settingRoutes from '@/routes/admin/settings';
import type {
    AdminProps,
    AdminUser,
    CatalogItem,
    ConciergeSummary,
    Limits,
} from '@/types/subscription';
import { euros } from '@/types/subscription';
const sections = [
    { key: 'overview', label: 'Vue d’ensemble', icon: LayoutDashboard },
    { key: 'concierges', label: 'Conciergeries', icon: Building2 },
    { key: 'plans', label: 'Offres et tarifs', icon: CreditCard },
    { key: 'addons', label: 'Options', icon: SlidersHorizontal },
    { key: 'users', label: 'Utilisateurs', icon: Users },
    { key: 'seo', label: 'SEO et promotion', icon: Search },
    { key: 'history', label: 'Historique', icon: FileClock },
    { key: 'settings', label: 'Paramètres', icon: Settings },
];
const statusLabels: Record<string, string> = {
    trialing: 'Essai',
    active: 'Actif',
    suspended: 'Suspendu',
    cancelled: 'Résilié',
};
const statuses = Object.entries(statusLabels).map(([value, label]) => ({
    value,
    label,
}));
function Status({ concierge }: { concierge: ConciergeSummary }) {
    return (
        <span
            className={
                'status-chip ' +
                (!concierge.usable
                    ? 'red'
                    : concierge.subscription.status === 'trialing'
                      ? 'amber'
                      : 'green')
            }
        >
            {!concierge.usable &&
            concierge.subscription.expires_at &&
            new Date(concierge.subscription.expires_at) < new Date()
                ? 'Expiré'
                : statusLabels[concierge.subscription.status]}
        </span>
    );
}
function numericMap(
    value: unknown,
    bonus = false,
): Record<string, number | null> {
    if (!value || typeof value !== 'object') return {};
    return Object.fromEntries(
        Object.entries(value).map(([k, v]) => [
            k,
            v === '' || v === null ? (bonus ? 0 : null) : Number(v),
        ]),
    );
}
function booleanMap(value: unknown): Record<string, boolean> {
    if (!value || typeof value !== 'object') return {};
    return Object.fromEntries(
        Object.entries(value)
            .filter(([, v]) => v !== '' && v !== null)
            .map(([k, v]) => [k, v === true || v === '1' || v === 1]),
    );
}
export default function Administration(props: AdminProps) {
    const { auth, success, name } = usePage<{ success?: string }>().props;
    const { url } = usePage();
    const rawSection =
        new URL(url, 'http://local').searchParams.get('view') ?? 'overview';
    const section = sections.some((s) => s.key === rawSection)
        ? rawSection
        : 'overview';
    const heading = sections.find((s) => s.key === section)!.label;
    const [search, setSearch] = useState('');
    const [filter, setFilter] = useState('');
    const [modal, setModal] = useState<
        'plan' | 'addon' | 'subscription' | 'user' | 'event' | null
    >(null);
    const [item, setItem] = useState<CatalogItem | null>(null);
    const [company, setCompany] = useState<ConciergeSummary | null>(null);
    const [user, setUser] = useState<AdminUser | null>(null);
    const [eventDetails, setEventDetails] = useState('');
    function close() {
        setModal(null);
        setItem(null);
        setCompany(null);
        setUser(null);
    }
    const visibleConcierges = props.concierges.filter(
        (c) =>
            c.name.toLowerCase().includes(search.toLowerCase()) &&
            (!filter || c.subscription.status === filter),
    );
    const visibleUsers = props.users.filter((u) =>
        (u.name + ' ' + u.email).toLowerCase().includes(search.toLowerCase()),
    );
    const active = props.concierges.filter(
        (c) => c.usable && c.subscription.status === 'active',
    );
    const revenue = active.reduce((sum, c) => sum + c.monthly_price_cents, 0);
    function editCompany(c: ConciergeSummary) {
        setCompany(c);
        setModal('subscription');
    }
    function editCatalog(c: CatalogItem, kind: 'plan' | 'addon') {
        setItem(c);
        setModal(kind);
    }
    return (
        <div className="workspace admin-workspace">
            <Head title={'Administration · ' + heading} />
            <aside className="workspace-sidebar admin-sidebar">
                <Brand />
                <div className="admin-role">
                    <ShieldCheck size={17} />
                    <span>Administration globale</span>
                </div>
                <nav className="workspace-nav" aria-label="Administration">
                    {sections.map(({ key, label, icon: Icon }) => (
                        <Link
                            href={adminRoutes.index({ query: { view: key } })}
                            key={key}
                            className={section === key ? 'active' : ''}
                        >
                            <Icon size={18} />
                            {label}
                        </Link>
                    ))}
                </nav>
                <div className="workspace-sidebar-footer">
                    <div className="admin-sidebar-note">
                        Offres, abonnements et accès à toute l’application.
                    </div>
                    <span>{name} · Administration</span>
                </div>
            </aside>
            <div className="workspace-main">
                <header className="workspace-topbar">
                    <span>
                        Administration <span className="mx-2">/</span> {heading}
                    </span>
                    <div>
                        <span className="user-avatar">
                            <ShieldCheck size={17} />
                        </span>
                        <span className="user-identity">
                            <b>{auth.user.name}</b>
                            <small>Administrateur de l’application</small>
                        </span>
                        <Link
                            href={logout()}
                            as="button"
                            aria-label="Se déconnecter"
                        >
                            <LogOut size={17} />
                        </Link>
                    </div>
                </header>
                <main className="workspace-content">
                    {success ? (
                        <div className="notice" role="status">
                            <Check size={17} />
                            {success}
                        </div>
                    ) : null}
                    <div className="workspace-page-heading">
                        <div>
                            <h1>{heading}</h1>
                            <p>
                                {section === 'overview'
                                    ? 'Pilotez les espaces, les offres et les capacités de votre application.'
                                    : section === 'plans'
                                      ? 'Le prix de base, les limites et les fonctionnalités de chaque formule.'
                                      : section === 'addons'
                                        ? 'Des capacités ou fonctionnalités supplémentaires, facturées par mois.'
                                        : section === 'concierges'
                                          ? 'Les conditions attribuées et l’usage réel de chaque entreprise.'
                                          : section === 'users'
                                            ? 'Comptes individuels, accès et administrateurs globaux.'
                                            : section === 'settings'
                                              ? 'Les règles d’inscription et de démarrage des nouveaux espaces.'
                                              : section === 'seo'
                                                ? 'Préparez votre visibilité : identité, pages publiques, indexation et suivi.'
                                                : 'Chaque changement d’offre ou d’accès reste traçable.'}
                            </p>
                        </div>
                        {section === 'plans' || section === 'addons' ? (
                            <button
                                className="brand-button small"
                                onClick={() => {
                                    setItem(null);
                                    setModal(
                                        section === 'plans' ? 'plan' : 'addon',
                                    );
                                }}
                            >
                                <Plus size={15} />
                                {section === 'plans'
                                    ? 'Nouvelle offre'
                                    : 'Nouvelle option'}
                            </button>
                        ) : null}
                    </div>
                    {section === 'overview' ? (
                        <>
                            <div className="stat-grid">
                                {[
                                    {
                                        label: 'Conciergeries',
                                        value: String(props.concierges.length),
                                        icon: Building2,
                                        sub: 'Espaces enregistrés',
                                    },
                                    {
                                        label: 'Abonnements actifs',
                                        value: String(active.length),
                                        icon: CreditCard,
                                        sub: 'Avec un accès en cours',
                                    },
                                    {
                                        label: 'Essais en cours',
                                        value: String(
                                            props.concierges.filter(
                                                (c) =>
                                                    c.usable &&
                                                    c.subscription.status ===
                                                        'trialing',
                                            ).length,
                                        ),
                                        icon: Activity,
                                        sub: 'À accompagner',
                                    },
                                    {
                                        label: 'Montant mensuel prévu',
                                        value: euros(revenue),
                                        icon: ShieldCheck,
                                        sub: 'Contrats actifs, hors essais',
                                    },
                                ].map(({ label, value, icon: Icon, sub }) => (
                                    <article
                                        className="workspace-card stat-card"
                                        key={label}
                                    >
                                        <span className="stat-icon">
                                            <Icon size={20} />
                                        </span>
                                        <div>
                                            <small>{label}</small>
                                            <b
                                                className={
                                                    value.length > 6
                                                        ? 'admin-small-stat'
                                                        : ''
                                                }
                                            >
                                                {value}
                                            </b>
                                            <span>{sub}</span>
                                        </div>
                                    </article>
                                ))}
                            </div>
                            <div className="dashboard-grid">
                                <section className="workspace-card">
                                    <div className="workspace-card-heading">
                                        <h2>Derniers espaces créés</h2>
                                        <Link
                                            href={adminRoutes.index({
                                                query: { view: 'concierges' },
                                            })}
                                        >
                                            Tout gérer →
                                        </Link>
                                    </div>
                                    {props.concierges.slice(0, 5).map((c) => (
                                        <button
                                            className="admin-company-row"
                                            key={c.id}
                                            onClick={() => editCompany(c)}
                                        >
                                            <span className="directory-icon">
                                                <Building2 size={20} />
                                            </span>
                                            <span>
                                                <b>{c.name}</b>
                                                <small>
                                                    {
                                                        c.subscription.terms
                                                            .plan_name
                                                    }
                                                </small>
                                            </span>
                                            <Status concierge={c} />
                                            <ArrowRight size={15} />
                                        </button>
                                    ))}
                                </section>
                                <section className="workspace-card">
                                    <div className="workspace-card-heading">
                                        <h2>À suivre</h2>
                                    </div>
                                    <div className="admin-followup">
                                        <b>
                                            {
                                                props.concierges.filter(
                                                    (c) => !c.usable,
                                                ).length
                                            }
                                        </b>
                                        <p>
                                            Espaces suspendus, résiliés ou
                                            arrivés à échéance.
                                        </p>
                                        <b>
                                            {
                                                props.concierges.filter((c) =>
                                                    Object.entries(
                                                        c.limits,
                                                    ).some(
                                                        ([key, limit]) =>
                                                            limit !== null &&
                                                            c.usage[key] >
                                                                limit,
                                                    ),
                                                ).length
                                            }
                                        </b>
                                        <p>
                                            Espaces au-dessus d’une limite après
                                            un changement d’offre. Leurs données
                                            sont conservées.
                                        </p>
                                    </div>
                                </section>
                            </div>
                            <section className="workspace-card mt-6">
                                <div className="workspace-card-heading">
                                    <h2>Configuration actuelle</h2>
                                    <Link
                                        href={adminRoutes.index({
                                            query: { view: 'settings' },
                                        })}
                                    >
                                        Modifier →
                                    </Link>
                                </div>
                                <div className="admin-settings-summary">
                                    <span>
                                        Inscriptions{' '}
                                        <b>
                                            {props.settings.registration_open
                                                ? 'Ouvertes'
                                                : 'Fermées'}
                                        </b>
                                    </span>
                                    <span>
                                        Période d’essai{' '}
                                        <b>{props.settings.trial_days} jours</b>
                                    </span>
                                    <span>
                                        Offre initiale{' '}
                                        <b>
                                            {
                                                props.plans.find(
                                                    (p) =>
                                                        p.id ===
                                                        props.settings
                                                            .default_plan_id,
                                                )?.name
                                            }
                                        </b>
                                    </span>
                                </div>
                            </section>
                        </>
                    ) : null}
                    {section === 'concierges' ? (
                        <>
                            <div className="admin-filters">
                                <div className="workspace-search">
                                    <Search size={16} />
                                    <input
                                        value={search}
                                        onChange={(e) =>
                                            setSearch(e.target.value)
                                        }
                                        placeholder="Rechercher une conciergerie…"
                                        aria-label="Rechercher une conciergerie"
                                    />
                                </div>
                                <select
                                    aria-label="État de l’abonnement"
                                    className="business-select"
                                    value={filter}
                                    onChange={(e) => setFilter(e.target.value)}
                                >
                                    <option value="">Tous les états</option>
                                    {statuses.map((s) => (
                                        <option key={s.value} value={s.value}>
                                            {s.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="workspace-card admin-table-wrap">
                                <table className="admin-table">
                                    <thead>
                                        <tr>
                                            <th>Conciergerie</th>
                                            <th>Offre et état</th>
                                            <th>Usage / limites</th>
                                            <th>Tarif mensuel</th>
                                            <th>Gestion</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {visibleConcierges.map((c) => (
                                            <tr key={c.id}>
                                                <td>
                                                    <b>{c.name}</b>
                                                    <small>
                                                        {c.members
                                                            .filter(
                                                                (m) =>
                                                                    m.role ===
                                                                    'manager',
                                                            )
                                                            .map(
                                                                (m) =>
                                                                    m.user.name,
                                                            )
                                                            .join(', ')}
                                                    </small>
                                                </td>
                                                <td>
                                                    <b>
                                                        {
                                                            c.subscription.terms
                                                                .plan_name
                                                        }
                                                    </b>
                                                    <Status concierge={c} />
                                                </td>
                                                <td>
                                                    {[
                                                        'staff',
                                                        'clients',
                                                        'properties',
                                                    ].map((key) => (
                                                        <div
                                                            className="admin-quota"
                                                            key={key}
                                                        >
                                                            <span>
                                                                {
                                                                    props
                                                                        .limitLabels[
                                                                        key
                                                                    ]
                                                                }
                                                            </span>
                                                            <b
                                                                className={
                                                                    c.limits[
                                                                        key
                                                                    ] !==
                                                                        null &&
                                                                    c.usage[
                                                                        key
                                                                    ] >=
                                                                        Number(
                                                                            c
                                                                                .limits[
                                                                                key
                                                                            ],
                                                                        )
                                                                        ? 'text-amber-700'
                                                                        : ''
                                                                }
                                                            >
                                                                {c.usage[key]} /{' '}
                                                                {c.limits[
                                                                    key
                                                                ] === null
                                                                    ? '∞'
                                                                    : c.limits[
                                                                          key
                                                                      ]}
                                                            </b>
                                                        </div>
                                                    ))}
                                                </td>
                                                <td>
                                                    <b>
                                                        {euros(
                                                            c.monthly_price_cents,
                                                        )}
                                                    </b>
                                                    {c.subscription
                                                        .expires_at ? (
                                                        <small>
                                                            Échéance{' '}
                                                            {new Date(
                                                                c.subscription
                                                                    .expires_at,
                                                            ).toLocaleDateString(
                                                                'fr-FR',
                                                            )}
                                                        </small>
                                                    ) : null}
                                                </td>
                                                <td>
                                                    <button
                                                        className="admin-text-button"
                                                        onClick={() =>
                                                            editCompany(c)
                                                        }
                                                    >
                                                        Gérer l’abonnement
                                                    </button>
                                                    <Form
                                                        action={conciergeRoutes.visit.url(
                                                            c.id,
                                                        )}
                                                        method="post"
                                                    >
                                                        <button
                                                            className="admin-text-button"
                                                            type="submit"
                                                        >
                                                            Ouvrir l’espace →
                                                        </button>
                                                    </Form>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                                {!visibleConcierges.length ? (
                                    <p className="admin-empty">
                                        Aucune conciergerie ne correspond à
                                        cette recherche.
                                    </p>
                                ) : null}
                            </div>
                        </>
                    ) : null}
                    {section === 'plans' || section === 'addons' ? (
                        <>
                            <div className="admin-info">
                                <ShieldCheck size={17} />
                                <p>
                                    Les conditions sont figées dans chaque
                                    abonnement. Les modifications du catalogue
                                    s’appliquent aux nouvelles attributions ;
                                    l’administrateur choisit explicitement de
                                    les réappliquer à un contrat existant.
                                </p>
                            </div>
                            <div className="directory-grid">
                                {(section === 'plans'
                                    ? props.plans
                                    : props.addons
                                ).map((c) => (
                                    <article
                                        className="workspace-card admin-plan-card"
                                        key={c.id}
                                    >
                                        <div className="flex items-center justify-between">
                                            <span
                                                className={
                                                    'status-chip ' +
                                                    (c.is_active
                                                        ? 'green'
                                                        : 'gray')
                                                }
                                            >
                                                {c.is_active
                                                    ? 'Disponible'
                                                    : 'Archivée'}
                                            </span>
                                            {section === 'plans' &&
                                            c.id ===
                                                props.settings
                                                    .default_plan_id ? (
                                                <small className="text-teal">
                                                    Offre initiale
                                                </small>
                                            ) : null}
                                        </div>
                                        <h2>{c.name}</h2>
                                        <p>{c.description}</p>
                                        <div className="admin-plan-price">
                                            {euros(c.monthly_price_cents)}
                                            <small>/ mois</small>
                                        </div>
                                        <div className="admin-plan-limits">
                                            {Object.entries(
                                                c.limits ?? c.limit_bonus ?? {},
                                            ).map(([key, value]) => (
                                                <div key={key}>
                                                    <span>
                                                        {props.limitLabels[key]}
                                                    </span>
                                                    <b>
                                                        {value === null
                                                            ? 'Illimité'
                                                            : section ===
                                                                'addons'
                                                              ? '+' + value
                                                              : value}
                                                    </b>
                                                </div>
                                            ))}
                                        </div>
                                        <div className="admin-plan-options">
                                            {Object.entries(props.featureLabels)
                                                .filter(
                                                    ([key]) => c.features[key],
                                                )
                                                .map(([key, label]) => (
                                                    <span key={key}>
                                                        <Check size={12} />
                                                        {label}
                                                    </span>
                                                ))}
                                        </div>
                                        <button
                                            className="brand-button small secondary mt-5 w-full"
                                            onClick={() =>
                                                editCatalog(
                                                    c,
                                                    section === 'plans'
                                                        ? 'plan'
                                                        : 'addon',
                                                )
                                            }
                                        >
                                            Modifier{' '}
                                            {section === 'plans'
                                                ? 'l’offre'
                                                : 'l’option'}
                                        </button>
                                    </article>
                                ))}
                            </div>
                            {!(section === 'plans' ? props.plans : props.addons)
                                .length ? (
                                <div className="workspace-card admin-empty">
                                    Créez une première{' '}
                                    {section === 'plans' ? 'offre' : 'option'}{' '}
                                    pour composer vos abonnements.
                                </div>
                            ) : null}
                        </>
                    ) : null}
                    {section === 'users' ? (
                        <>
                            <div className="workspace-search">
                                <Search size={16} />
                                <input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Rechercher une personne…"
                                    aria-label="Rechercher une personne"
                                />
                            </div>
                            <div className="workspace-card admin-table-wrap">
                                <table className="admin-table">
                                    <thead>
                                        <tr>
                                            <th>Personne</th>
                                            <th>Conciergeries</th>
                                            <th>Accès</th>
                                            <th>Gestion</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {visibleUsers.map((u) => (
                                            <tr key={u.id}>
                                                <td>
                                                    <b>{u.name}</b>
                                                    <small>{u.email}</small>
                                                </td>
                                                <td>
                                                    {props.concierges
                                                        .filter((c) =>
                                                            c.members.some(
                                                                (m) =>
                                                                    m.user
                                                                        .id ===
                                                                    u.id,
                                                            ),
                                                        )
                                                        .map((c) => (
                                                            <small key={c.id}>
                                                                {c.name}
                                                            </small>
                                                        ))}
                                                </td>
                                                <td>
                                                    <span
                                                        className={
                                                            'status-chip ' +
                                                            (u.disabled_at
                                                                ? 'red'
                                                                : u.is_admin
                                                                  ? 'blue'
                                                                  : 'green')
                                                        }
                                                    >
                                                        {u.disabled_at
                                                            ? 'Suspendu'
                                                            : u.is_admin
                                                              ? 'Administrateur global'
                                                              : 'Compte actif'}
                                                    </span>
                                                </td>
                                                <td>
                                                    <button
                                                        className="admin-text-button"
                                                        onClick={() => {
                                                            setUser(u);
                                                            setModal('user');
                                                        }}
                                                    >
                                                        Gérer les accès
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </>
                    ) : null}
                    {section === 'settings' ? (
                        <section className="workspace-card admin-settings-card">
                            <h2>Inscription et offre initiale</h2>
                            <BusinessForm
                                action={settingRoutes.update.url()}
                                transform={(data) => ({
                                    ...data,
                                    registration_open:
                                        'registration_open' in data,
                                })}
                            >
                                <SelectField
                                    label="Offre attribuée aux nouvelles conciergeries"
                                    name="default_plan_id"
                                    items={props.plans
                                        .filter((p) => p.is_active)
                                        .map((p) => ({
                                            value: p.id,
                                            label: p.name,
                                        }))}
                                    defaultValue={
                                        props.settings.default_plan_id
                                    }
                                    required
                                    empty=""
                                />
                                <Field
                                    label="Durée de l’essai (jours)"
                                    name="trial_days"
                                    type="number"
                                    min={1}
                                    max={365}
                                    required
                                    defaultValue={props.settings.trial_days}
                                />
                                <label className="admin-checkbox">
                                    <input
                                        name="registration_open"
                                        type="checkbox"
                                        defaultChecked={
                                            props.settings.registration_open
                                        }
                                        value="1"
                                    />
                                    Autoriser la création de nouveaux espaces
                                </label>
                                <p className="form-hint">
                                    Ces paramètres s’appliquent aux nouveaux
                                    espaces. Les abonnements existants
                                    conservent leurs dates et conditions.
                                </p>
                            </BusinessForm>
                        </section>
                    ) : null}
                    {section === 'seo' ? (
                        <SeoAdministration data={props.seoManagement} />
                    ) : null}
                    {section === 'history' ? (
                        <section className="workspace-card">
                            <div className="workspace-card-heading">
                                <h2>Journal d’administration</h2>
                                <small className="text-slate-500">
                                    100 derniers événements
                                </small>
                            </div>
                            {props.events.map((e) => (
                                <button
                                    className="admin-event"
                                    key={e.id}
                                    onClick={() => {
                                        setEventDetails(
                                            e.details
                                                ? JSON.stringify(
                                                      JSON.parse(e.details),
                                                      null,
                                                      2,
                                                  )
                                                : 'Aucun détail complémentaire.',
                                        );
                                        setModal('event');
                                    }}
                                >
                                    <FileClock size={17} />
                                    <span>
                                        <b>{e.description}</b>
                                        <small>
                                            {e.actor} ·{' '}
                                            {new Date(
                                                e.created_at,
                                            ).toLocaleString('fr-FR')}
                                        </small>
                                    </span>
                                    <ArrowRight size={14} />
                                </button>
                            ))}
                            {!props.events.length ? (
                                <p className="admin-empty">
                                    Les changements d’offres, de contrats et
                                    d’accès apparaîtront ici.
                                </p>
                            ) : null}
                        </section>
                    ) : null}
                </main>
            </div>
            <Dialog
                open={modal !== null}
                onOpenChange={(value) => {
                    if (!value) close();
                }}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            {modal === 'plan'
                                ? item
                                    ? 'Modifier l’offre'
                                    : 'Créer une offre'
                                : modal === 'addon'
                                  ? item
                                      ? 'Modifier l’option'
                                      : 'Créer une option'
                                  : modal === 'subscription'
                                    ? 'Gérer l’abonnement'
                                    : modal === 'user'
                                      ? 'Accès utilisateur'
                                      : 'Détails du changement'}
                        </DialogTitle>
                        <DialogDescription>
                            {modal === 'subscription'
                                ? company?.name
                                : 'Administration de ' + name}
                        </DialogDescription>
                    </DialogHeader>
                    {modal === 'plan' || modal === 'addon' ? (
                        <CatalogForm
                            item={item}
                            addon={modal === 'addon'}
                            labels={props.limitLabels}
                            featureLabels={props.featureLabels}
                            close={close}
                        />
                    ) : null}
                    {modal === 'subscription' && company ? (
                        <SubscriptionForm
                            company={company}
                            plans={props.plans}
                            addons={props.addons}
                            labels={props.limitLabels}
                            featureLabels={props.featureLabels}
                            close={close}
                        />
                    ) : null}
                    {modal === 'user' && user ? (
                        <BusinessForm
                            action={userRoutes.update.url(user.id)}
                            onSuccess={close}
                            transform={(data) => ({
                                ...data,
                                is_admin: 'is_admin' in data,
                                disabled: 'disabled' in data,
                            })}
                        >
                            <div className="detail-block">
                                <b>{user.name}</b>
                                <br />
                                {user.email}
                            </div>
                            <label className="admin-checkbox">
                                <input
                                    type="checkbox"
                                    name="is_admin"
                                    value="1"
                                    defaultChecked={user.is_admin}
                                />
                                Administrateur global de l’application
                            </label>
                            <label className="admin-checkbox">
                                <input
                                    type="checkbox"
                                    name="disabled"
                                    value="1"
                                    defaultChecked={Boolean(user.disabled_at)}
                                />
                                Suspendre l’accès de cette personne
                            </label>
                            <p className="form-hint">
                                Un administrateur global gère toutes les
                                conciergeries, leurs offres et leurs
                                utilisateurs. Vous conservez votre propre accès
                                administrateur.
                            </p>
                        </BusinessForm>
                    ) : null}
                    {modal === 'event' ? (
                        <pre className="admin-event-details">
                            {eventDetails}
                        </pre>
                    ) : null}
                </DialogContent>
            </Dialog>
        </div>
    );
}
function CatalogForm({
    item,
    addon,
    labels,
    featureLabels,
    close,
}: {
    item: CatalogItem | null;
    addon: boolean;
    labels: Record<string, string>;
    featureLabels: Record<string, string>;
    close: () => void;
}) {
    const action = addon
        ? item
            ? addonRoutes.update.url(item.id)
            : addonRoutes.store.url()
        : item
          ? planRoutes.update.url(item.id)
          : planRoutes.store.url();
    const limitsKey = addon ? 'limit_bonus' : 'limits';
    return (
        <BusinessForm
            action={action}
            onSuccess={close}
            transform={(data) => ({
                ...data,
                is_active: 'is_active' in data,
                ...(!addon ? { is_public: 'is_public' in data } : {}),
                [limitsKey]: numericMap(
                    limitsKey in data
                        ? data[limitsKey as keyof typeof data]
                        : {},
                    addon,
                ),
                features: Object.fromEntries(
                    Object.keys(featureLabels).map((key) => [
                        key,
                        Boolean(
                            'features' in data &&
                            data.features &&
                            typeof data.features === 'object' &&
                            key in data.features,
                        ),
                    ]),
                ),
            })}
        >
            <Field
                label="Nom de l’offre / option"
                name="name"
                defaultValue={item?.name}
                required
            />
            <TextArea
                label="Description"
                name="description"
                defaultValue={item?.description ?? ''}
            />
            <label className="form-field">
                Tarif mensuel en euros
                <input
                    name="monthly_price"
                    type="number"
                    step="0.01"
                    min="0"
                    max="999999"
                    required
                    defaultValue={item ? item.monthly_price_cents / 100 : 0}
                />
            </label>
            <div className="form-grid">
                {Object.entries(labels).map(([key, label]) => (
                    <Field
                        key={key}
                        label={
                            addon
                                ? label + ' supplémentaires'
                                : label + ' (vide = illimité)'
                        }
                        name={limitsKey + '[' + key + ']'}
                        type="number"
                        min={0}
                        max={1000000}
                        defaultValue={
                            addon
                                ? (item?.limit_bonus?.[key] ?? 0)
                                : (item?.limits?.[key] ?? '')
                        }
                    />
                ))}
            </div>
            <div className="admin-feature-grid">
                {Object.entries(featureLabels).map(([key, label]) => (
                    <label className="admin-checkbox" key={key}>
                        <input
                            name={'features[' + key + ']'}
                            type="checkbox"
                            value="1"
                            defaultChecked={item?.features[key] ?? false}
                        />
                        {label}
                    </label>
                ))}
            </div>
            <label className="admin-checkbox">
                <input
                    name="is_active"
                    type="checkbox"
                    value="1"
                    defaultChecked={item?.is_active ?? true}
                />
                Disponible pour de nouvelles attributions
            </label>
            {!addon ? (
                <label className="admin-checkbox">
                    <input
                        name="is_public"
                        type="checkbox"
                        value="1"
                        defaultChecked={item?.is_public ?? false}
                    />
                    Afficher cette offre sur la page des tarifs
                </label>
            ) : null}
            <p className="form-hint">
                {addon
                    ? 'Les options ajoutent leur montant et leurs capacités à l’offre de base. Une capacité déjà illimitée le reste.'
                    : 'Les quotas sont contrôlés côté serveur. Une limite à zéro bloque tout nouvel ajout ; un champ vide signifie illimité.'}
            </p>
        </BusinessForm>
    );
}
function SubscriptionForm({
    company,
    plans,
    addons,
    labels,
    featureLabels,
    close,
}: {
    company: ConciergeSummary;
    plans: CatalogItem[];
    addons: CatalogItem[];
    labels: Record<string, string>;
    featureLabels: Record<string, string>;
    close: () => void;
}) {
    const s = company.subscription;
    const [overrides, setOverrides] = useState(() =>
        Object.fromEntries(
            Object.keys(labels).map((key) => [
                key,
                Object.hasOwn(s.limit_overrides ?? {}, key),
            ]),
        ),
    );
    return (
        <BusinessForm
            action={subscriptionRoutes.update.url(company.id)}
            onSuccess={close}
            submit="Appliquer à cette conciergerie"
            transform={(data) => ({
                ...data,
                addon_ids:
                    'addon_ids' in data && Array.isArray(data.addon_ids)
                        ? data.addon_ids.map(Number)
                        : [],
                limit_overrides: numericMap(
                    'limit_overrides' in data ? data.limit_overrides : {},
                ),
                feature_overrides: booleanMap(
                    'feature_overrides' in data ? data.feature_overrides : {},
                ),
                refresh_terms: 'refresh_terms' in data,
            })}
        >
            <div className="admin-current-contract">
                <b>Conditions actuellement attribuées</b>
                <p>
                    {s.terms.plan_name} · {euros(company.monthly_price_cents)} /
                    mois
                </p>
            </div>
            <div className="form-grid">
                <SelectField
                    label="Offre de base"
                    name="plan_id"
                    required
                    empty=""
                    defaultValue={s.subscription_plan_id}
                    items={plans
                        .filter(
                            (p) =>
                                p.is_active || p.id === s.subscription_plan_id,
                        )
                        .map((p) => ({
                            value: p.id,
                            label: p.name + (p.is_active ? '' : ' (archivée)'),
                        }))}
                />
                <SelectField
                    label="État"
                    name="status"
                    required
                    empty=""
                    defaultValue={s.status}
                    items={statuses}
                />
            </div>
            <div className="form-grid">
                <Field
                    label="Début de l’abonnement"
                    name="starts_on"
                    type="date"
                    required
                    defaultValue={s.starts_on}
                />
                <Field
                    label="Échéance (vide = sans échéance)"
                    name="expires_at"
                    type="datetime-local"
                    defaultValue={
                        s.expires_at ? localDateTime(s.expires_at) : ''
                    }
                />
            </div>
            <div>
                <h3 className="mb-3 text-sm font-semibold">
                    Options supplémentaires
                </h3>
                {addons.length ? (
                    addons
                        .filter(
                            (a) =>
                                a.is_active ||
                                s.terms.addons.some((x) => x.id === a.id),
                        )
                        .map((a) => (
                            <label className="admin-checkbox mb-3" key={a.id}>
                                <input
                                    name="addon_ids[]"
                                    type="checkbox"
                                    value={a.id}
                                    defaultChecked={s.terms.addons.some(
                                        (x) => x.id === a.id,
                                    )}
                                />
                                {a.name}
                                <span className="ml-auto text-xs">
                                    + {euros(a.monthly_price_cents)} / mois
                                </span>
                            </label>
                        ))
                ) : (
                    <p className="form-hint">
                        Créez les options dans le catalogue pour les attribuer
                        ici.
                    </p>
                )}
            </div>
            <label className="admin-checkbox">
                <input name="refresh_terms" type="checkbox" value="1" />
                Réappliquer les tarifs et limites actuels du catalogue
            </label>
            <p className="form-hint">
                Changer d’offre ou d’options crée de nouvelles conditions. Pour
                une simple suspension ou dérogation, les conditions actuelles
                sont conservées.
            </p>
            <details
                className="admin-advanced"
                open={Object.keys(s.limit_overrides ?? {}).length > 0}
            >
                <summary>Dérogations propres à cette conciergerie</summary>
                <div className="form-grid mt-4">
                    {Object.entries(labels).map(([key, label]) => (
                        <div key={key}>
                            <label className="admin-checkbox mb-2">
                                <input
                                    type="checkbox"
                                    checked={overrides[key]}
                                    onChange={(e) =>
                                        setOverrides((current) => ({
                                            ...current,
                                            [key]: e.target.checked,
                                        }))
                                    }
                                />
                                Remplacer : {label}
                            </label>
                            {overrides[key] ? (
                                <Field
                                    name={'limit_overrides[' + key + ']'}
                                    label="Limite personnalisée (vide = illimité)"
                                    type="number"
                                    min={0}
                                    max={1000000}
                                    defaultValue={
                                        s.limit_overrides?.[key] ?? ''
                                    }
                                />
                            ) : (
                                <p className="form-hint">
                                    Selon l’offre :{' '}
                                    {company.limits[key] === null
                                        ? 'Illimité'
                                        : company.limits[key]}
                                </p>
                            )}
                        </div>
                    ))}
                </div>
                <div className="admin-feature-grid mt-5">
                    {Object.entries(featureLabels).map(([key, label]) => (
                        <SelectField
                            key={key}
                            name={'feature_overrides[' + key + ']'}
                            label={label}
                            empty="Selon l’offre"
                            defaultValue={
                                Object.hasOwn(s.feature_overrides ?? {}, key)
                                    ? s.feature_overrides?.[key]
                                        ? '1'
                                        : '0'
                                    : ''
                            }
                            items={[
                                {
                                    value: '1',
                                    label: 'Activer pour cet espace',
                                },
                                {
                                    value: '0',
                                    label: 'Désactiver pour cet espace',
                                },
                            ]}
                        />
                    ))}
                </div>
                <label className="form-field mt-5">
                    Tarif mensuel personnalisé (vide = tarif de l’offre et des
                    options)
                    <input
                        name="price_override"
                        type="number"
                        step="0.01"
                        min="0"
                        max="999999"
                        defaultValue={
                            s.price_override_cents === null
                                ? ''
                                : s.price_override_cents / 100
                        }
                    />
                </label>
            </details>
            <TextArea
                name="notes"
                label="Notes internes d’administration"
                defaultValue={s.notes ?? ''}
            />
        </BusinessForm>
    );
}
function localDateTime(value: string) {
    const date = new Date(value);
    return (
        date.getFullYear() +
        '-' +
        String(date.getMonth() + 1).padStart(2, '0') +
        '-' +
        String(date.getDate()).padStart(2, '0') +
        'T' +
        String(date.getHours()).padStart(2, '0') +
        ':' +
        String(date.getMinutes()).padStart(2, '0')
    );
}
