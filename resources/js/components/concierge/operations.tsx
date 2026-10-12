import { Link, router, useForm, usePoll } from '@inertiajs/react';
import {
    Bell,
    Check,
    ChevronRight,
    Download,
    Plus,
    RefreshCw,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { BusinessForm, Field, SelectField, TextArea } from './business-form';
import * as notificationRoutes from '@/routes/notifications';
import * as recurrenceRoutes from '@/routes/recurrences';
import * as occurrenceRoutes from '@/routes/occurrences';
import * as availabilityRoutes from '@/routes/availabilities';
import * as incidentRoutes from '@/routes/incidents';
import * as reportRoutes from '@/routes/reports';
import * as clientRoutes from '@/routes/clients';
import * as photoRoutes from '@/routes/photos';
import { dashboard } from '@/routes';
import type { IncidentRecord, WorkspaceProps } from '@/types/concierge';
import type { NotificationInbox } from '@/types/operations';

const when = (value: string) =>
    new Date(value).toLocaleString('fr-FR', {
        timeZone: 'Europe/Paris',
        dateStyle: 'medium',
        timeStyle: 'short',
    });
const day = (value: string) =>
    new Date(value.slice(0, 10) + 'T12:00:00').toLocaleDateString('fr-FR', {
        dateStyle: 'medium',
    });

export function NotificationBell({
    inbox,
    identity,
}: {
    inbox: NotificationInbox;
    identity: string;
}) {
    usePoll(15000, { only: ['notificationInbox'] });
    const seen = useRef<{ identity: string; id: number } | null>(null);
    useEffect(() => {
        const max = Math.max(0, ...inbox.items.map((n) => n.id));
        if (
            seen.current?.identity === identity &&
            'Notification' in window &&
            Notification.permission === 'granted'
        ) {
            for (const item of inbox.items.filter(
                (n) =>
                    n.id > seen.current!.id &&
                    !n.read_at &&
                    inbox.preferences.browser_events.includes(n.event),
            )) {
                try {
                    const alert = new Notification(item.title, {
                        body: item.body,
                        tag: identity + ':' + item.id,
                        icon: '/favicon.svg',
                    });
                    alert.onclick = () => {
                        window.focus();
                        router.visit(item.url);
                        alert.close();
                    };
                } catch {
                    /* The in-app inbox remains available on devices without native alerts. */
                }
            }
        }
        seen.current = { identity, id: max };
    }, [inbox, identity]);
    return (
        <Link
            href={dashboard({ query: { view: 'notifications' } })}
            className="operation-bell"
            aria-label={'Notifications : ' + inbox.unread + ' non lues'}
        >
            <Bell size={18} />
            {inbox.unread > 0 && (
                <span>{inbox.unread > 99 ? '99+' : inbox.unread}</span>
            )}
        </Link>
    );
}

export function NotificationsPanel({ inbox }: { inbox: NotificationInbox }) {
    const [onlyUnread, setOnlyUnread] = useState(false);
    const [permission, setPermission] = useState('');
    const form = useForm({ ...inbox.preferences });
    const toggle = (key: 'email_events' | 'browser_events', event: string) =>
        form.setData(
            key,
            form.data[key].includes(event)
                ? form.data[key].filter((x) => x !== event)
                : [...form.data[key], event],
        );
    return (
        <div className="operations-stack">
            <section className="workspace-card">
                <div className="workspace-card-heading">
                    <h2>Votre historique d’alertes</h2>
                    <div className="operation-actions">
                        <label>
                            <input
                                type="checkbox"
                                checked={onlyUnread}
                                onChange={(e) =>
                                    setOnlyUnread(e.target.checked)
                                }
                            />{' '}
                            Non lues
                        </label>
                        <button
                            onClick={() =>
                                router.post(notificationRoutes.readAll.url())
                            }
                        >
                            Tout marquer comme lu
                        </button>
                    </div>
                </div>
                <div className="notification-list">
                    {inbox.items
                        .filter((n) => !onlyUnread || !n.read_at)
                        .map((item) => (
                            <article
                                key={item.id}
                                className={item.read_at ? '' : 'unread'}
                            >
                                <span className="notification-marker" />
                                <div>
                                    <b>{item.title}</b>
                                    <p>{item.body}</p>
                                    <small>{when(item.created_at)}</small>
                                </div>
                                <button
                                    className="terrain-link"
                                    onClick={() =>
                                        router.post(
                                            notificationRoutes.read.url(
                                                item.id,
                                            ),
                                            {},
                                            {
                                                onSuccess: () =>
                                                    router.visit(item.url),
                                            },
                                        )
                                    }
                                >
                                    Consulter <ChevronRight size={15} />
                                </button>
                            </article>
                        ))}
                    {!inbox.items.filter((n) => !onlyUnread || !n.read_at)
                        .length && (
                        <p className="operation-empty">
                            Aucune notification à afficher.
                        </p>
                    )}
                </div>
            </section>
            <section className="workspace-card operation-settings">
                <h2>Choisir les alertes reçues</h2>
                <p className="form-hint">
                    L’historique dans l’application est conservé, quels que
                    soient ces réglages. Les emails nécessitent un service
                    d’envoi configuré. Les alertes navigateur fonctionnent
                    lorsque cet espace est ouvert.
                </p>
                <button
                    className="brand-button secondary small mt-4"
                    onClick={async () =>
                        setPermission(
                            !('Notification' in window)
                                ? 'Ce navigateur ne propose pas les alertes natives.'
                                : (await Notification.requestPermission()) ===
                                    'granted'
                                  ? 'Alertes autorisées sur cet appareil.'
                                  : 'Les alertes natives ne sont pas autorisées.',
                        )
                    }
                >
                    <Bell size={14} />
                    Autoriser les alertes sur cet appareil
                </button>
                {permission && (
                    <p className="form-hint mt-3" role="status">
                        {permission}
                    </p>
                )}
                <form
                    className="business-form mt-5"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(notificationRoutes.preferences.url(), {
                            preserveScroll: true,
                        });
                    }}
                >
                    <div className="notification-preferences">
                        <div>
                            <b>Événement</b>
                            <b>Email</b>
                            <b>Navigateur</b>
                        </div>
                        {Object.entries(inbox.eventLabels).map(
                            ([event, label]) => (
                                <div key={event}>
                                    <span>{label}</span>
                                    <input
                                        aria-label={label + ' : email'}
                                        type="checkbox"
                                        checked={form.data.email_events.includes(
                                            event,
                                        )}
                                        onChange={() =>
                                            toggle('email_events', event)
                                        }
                                    />
                                    <input
                                        aria-label={label + ' : navigateur'}
                                        type="checkbox"
                                        checked={form.data.browser_events.includes(
                                            event,
                                        )}
                                        onChange={() =>
                                            toggle('browser_events', event)
                                        }
                                    />
                                </div>
                            ),
                        )}
                    </div>
                    {Object.values(form.errors).map((error, i) => (
                        <p className="form-errors" key={i}>
                            {error}
                        </p>
                    ))}
                    <button
                        className="brand-button small"
                        disabled={form.processing}
                    >
                        Enregistrer mes préférences
                    </button>
                </form>
            </section>
        </div>
    );
}

export function RecurrencesPanel({ props }: { props: WorkspaceProps }) {
    const [adding, setAdding] = useState(false);
    return (
        <div className="operations-stack">
            <div className="operation-actions">
                <button
                    className="brand-button small"
                    onClick={() => setAdding(!adding)}
                >
                    <Plus size={15} />
                    Nouvelle récurrence
                </button>
                <button
                    className="brand-button secondary small"
                    onClick={() => router.post(recurrenceRoutes.generate.url())}
                >
                    <RefreshCw size={15} />
                    Préparer les 14 prochains jours
                </button>
            </div>
            {adding && (
                <section className="workspace-card operation-settings">
                    <h2>Préparer des interventions régulières</h2>
                    <BusinessForm
                        action={recurrenceRoutes.store.url()}
                        onSuccess={() => setAdding(false)}
                    >
                        <Field
                            label="Nom de la récurrence"
                            name="title"
                            required
                        />
                        <div className="admin-feature-grid">
                            <SelectField
                                label="Lieu"
                                name="property_id"
                                required
                                items={props.properties.map((p) => ({
                                    value: p.id,
                                    label: p.name,
                                }))}
                            />
                            <SelectField
                                label="Modèle de prestation"
                                name="template_id"
                                required
                                items={props.templates.map((p) => ({
                                    value: p.id,
                                    label: p.name,
                                }))}
                            />
                        </div>
                        <SelectField
                            label="Intervenant proposé (facultatif)"
                            name="assigned_user_id"
                            items={props.members
                                .filter((m) =>
                                    ['manager', 'worker'].includes(m.role),
                                )
                                .map((m) => ({
                                    value: m.user_id,
                                    label: m.user.name,
                                }))}
                        />
                        <input type="hidden" name="kind" value="service" />
                        <div className="admin-feature-grid">
                            <SelectField
                                label="Rythme"
                                name="frequency"
                                required
                                empty=""
                                defaultValue="weekly"
                                items={[
                                    { value: 'daily', label: 'Jours' },
                                    { value: 'weekly', label: 'Semaines' },
                                    { value: 'monthly', label: 'Mois' },
                                ]}
                            />
                            <Field
                                label="Tous les…"
                                name="interval"
                                type="number"
                                min={1}
                                max={12}
                                defaultValue={1}
                                required
                            />
                        </div>
                        <div className="admin-feature-grid">
                            <Field
                                label="Premier passage"
                                name="starts_on"
                                type="date"
                                required
                            />
                            <Field
                                label="Dernier passage (facultatif)"
                                name="ends_on"
                                type="date"
                            />
                        </div>
                        <div className="admin-feature-grid">
                            <Field
                                label="Heure (Paris)"
                                name="time"
                                type="time"
                                defaultValue="09:00"
                                required
                            />
                            <Field
                                label="Durée (minutes)"
                                name="duration"
                                type="number"
                                min={5}
                                max={1440}
                                defaultValue={60}
                                required
                            />
                        </div>
                        <TextArea label="Consignes" name="instructions" />
                        <p className="form-hint">
                            Chaque proposition nécessite une confirmation avant
                            de devenir une mission. Pour un passage mensuel le
                            31, les mois plus courts utilisent leur dernier
                            jour.
                        </p>
                    </BusinessForm>
                </section>
            )}
            <section className="workspace-card">
                <div className="workspace-card-heading">
                    <h2>Propositions à confirmer</h2>
                    <small>{props.occurrences.length} en attente</small>
                </div>
                <div className="operation-list">
                    {props.occurrences.map((item) => (
                        <article key={item.id}>
                            <div>
                                <b>{item.snapshot.title}</b>
                                <p>
                                    {when(item.snapshot.scheduled_at)} ·{' '}
                                    {item.snapshot.duration} min ·{' '}
                                    {
                                        props.properties.find(
                                            (p) =>
                                                p.id ===
                                                item.snapshot.property_id,
                                        )?.name
                                    }
                                </p>
                                {!item.recurrence.is_active && (
                                    <span className="status-chip amber">
                                        Récurrence suspendue
                                    </span>
                                )}
                            </div>
                            <div className="operation-actions">
                                {item.recurrence.is_active && (
                                    <button
                                        className="brand-button small"
                                        onClick={() =>
                                            router.post(
                                                occurrenceRoutes.decide.url(
                                                    item.id,
                                                ),
                                                { decision: 'confirmed' },
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <Check size={14} />
                                        Confirmer
                                    </button>
                                )}
                                <button
                                    className="brand-button secondary small"
                                    onClick={() =>
                                        router.post(
                                            occurrenceRoutes.decide.url(
                                                item.id,
                                            ),
                                            { decision: 'skipped' },
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    Ignorer
                                </button>
                            </div>
                        </article>
                    ))}
                    {!props.occurrences.length && (
                        <p className="operation-empty">
                            Aucune proposition en attente. Ajoutez une
                            récurrence pour préparer vos passages.
                        </p>
                    )}
                </div>
            </section>
            <section className="workspace-card">
                <div className="workspace-card-heading">
                    <h2>Règles enregistrées</h2>
                </div>
                <div className="operation-list">
                    {props.recurrences.map((rule) => (
                        <article key={rule.id}>
                            <div>
                                <b>{rule.title}</b>
                                <p>
                                    {rule.property.name} ·{' '}
                                    {rule.interval === 1
                                        ? 'Chaque'
                                        : rule.frequency === 'weekly'
                                          ? 'Toutes les'
                                          : 'Tous les'}{' '}
                                    {rule.interval !== 1 && `${rule.interval} `}
                                    {
                                        {
                                            daily:
                                                rule.interval === 1
                                                    ? 'jour'
                                                    : 'jours',
                                            weekly:
                                                rule.interval === 1
                                                    ? 'semaine'
                                                    : 'semaines',
                                            monthly: 'mois',
                                        }[rule.frequency]
                                    }{' '}
                                    à {rule.time}
                                </p>
                                <small>
                                    Prochaine préparation : {day(rule.next_on)}
                                </small>
                            </div>
                            <button
                                className="brand-button secondary small"
                                onClick={() =>
                                    router.post(
                                        recurrenceRoutes.toggle.url(rule.id),
                                        { is_active: !rule.is_active },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {rule.is_active ? 'Suspendre' : 'Reprendre'}
                            </button>
                        </article>
                    ))}
                    {!props.recurrences.length && (
                        <p className="operation-empty">
                            Aucune règle enregistrée.
                        </p>
                    )}
                </div>
            </section>
        </div>
    );
}

export function AvailabilityPanel({
    props,
    user,
}: {
    props: WorkspaceProps;
    user: { id: number; name: string };
}) {
    const [date, setDate] = useState(() =>
        new Date().toLocaleDateString('fr-CA', { timeZone: 'Europe/Paris' }),
    );
    const people = props.members
        .filter((m) => ['manager', 'worker'].includes(m.role))
        .map((m) => ({ id: m.user_id, name: m.user.name }));
    const team = people.length ? people : [user];
    const missions = props.missions.filter(
        (m) =>
            !['cancelled', 'validated'].includes(m.status) &&
            new Date(m.scheduled_at).toLocaleDateString('fr-CA', {
                timeZone: 'Europe/Paris',
            }) === date,
    );
    return (
        <div className="operations-stack">
            <section className="workspace-card operation-settings">
                <h2>Charge prévue de l’équipe</h2>
                <label className="form-field mt-4">
                    Journée
                    <input
                        type="date"
                        value={date}
                        onChange={(e) => setDate(e.target.value)}
                    />
                </label>
                <div className="workload-grid">
                    {team.map((person) => (
                        <article key={person.id}>
                            <b>{person.name}</b>
                            <strong>
                                {
                                    missions.filter(
                                        (m) => m.assigned_user_id === person.id,
                                    ).length
                                }{' '}
                                {missions.filter(
                                    (m) => m.assigned_user_id === person.id,
                                ).length > 1
                                    ? 'missions'
                                    : 'mission'}
                            </strong>
                            <p>
                                {Math.round(
                                    (missions
                                        .filter(
                                            (m) =>
                                                m.assigned_user_id ===
                                                person.id,
                                        )
                                        .reduce((n, m) => n + m.duration, 0) /
                                        60) *
                                        10,
                                ) / 10}{' '}
                                h planifiées
                            </p>
                            {missions
                                .filter((m) => m.assigned_user_id === person.id)
                                .map((m) => (
                                    <p key={m.id}>
                                        {when(m.scheduled_at)} · {m.title}
                                    </p>
                                ))}
                        </article>
                    ))}
                </div>
            </section>
            <section className="workspace-card operation-settings">
                <h2>Enregistrer une indisponibilité</h2>
                <BusinessForm action={availabilityRoutes.store.url()}>
                    <SelectField
                        label="Personne"
                        name="user_id"
                        required
                        empty=""
                        defaultValue={team[0]?.id}
                        items={team.map((p) => ({
                            value: p.id,
                            label: p.name,
                        }))}
                    />
                    <div className="admin-feature-grid">
                        <Field
                            label="Début (Paris)"
                            name="starts_at"
                            type="datetime-local"
                            required
                        />
                        <Field
                            label="Fin (Paris)"
                            name="ends_at"
                            type="datetime-local"
                            required
                        />
                    </div>
                    <Field
                        label="Précision facultative"
                        name="note"
                        placeholder="Congé, déplacement…"
                    />
                    <p className="form-hint">
                        Une nouvelle affectation sera bloquée si elle chevauche
                        cette période ou une autre mission. Les missions
                        existantes restent à réorganiser.
                    </p>
                </BusinessForm>
            </section>
            <section className="workspace-card">
                <div className="workspace-card-heading">
                    <h2>Périodes enregistrées</h2>
                </div>
                <div className="operation-list">
                    {props.availabilities.map((period) => (
                        <article key={period.id}>
                            <div>
                                <b>{period.user.name}</b>
                                <p>
                                    {when(period.starts_at)} →{' '}
                                    {when(period.ends_at)}
                                </p>
                                <small>{period.note}</small>
                                {props.missions.some(
                                    (m) =>
                                        m.assigned_user_id === period.user_id &&
                                        !['cancelled', 'validated'].includes(
                                            m.status,
                                        ) &&
                                        new Date(m.scheduled_at) <
                                            new Date(period.ends_at) &&
                                        new Date(
                                            new Date(m.scheduled_at).getTime() +
                                                m.duration * 60000,
                                        ) > new Date(period.starts_at),
                                ) && (
                                    <p className="seo-warning">
                                        Une mission existante chevauche cette
                                        indisponibilité.
                                    </p>
                                )}
                            </div>
                            <button
                                className="terrain-link"
                                onClick={() =>
                                    router.delete(
                                        availabilityRoutes.destroy.url(
                                            period.id,
                                        ),
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Retirer
                            </button>
                        </article>
                    ))}
                    {!props.availabilities.length && (
                        <p className="operation-empty">
                            Aucune indisponibilité enregistrée.
                        </p>
                    )}
                </div>
            </section>
        </div>
    );
}

export function IncidentFollowUp({
    incident,
    props,
}: {
    incident: IncidentRecord;
    props: WorkspaceProps;
}) {
    const [mode, setMode] = useState('');
    const repair = props.missions.find(
        (m) => m.id === incident.repair_mission_id,
    );
    const photos =
        (
            repair ?? props.missions.find((m) => m.id === incident.mission_id)
        )?.reports
            .filter((r) => r.status === 'validated')
            .flatMap((r) => r.photos) ?? [];
    const staff = props.members
        .filter((m) => ['manager', 'worker'].includes(m.role))
        .map((m) => ({ value: m.user_id, label: m.user.name }));
    return (
        <div className="incident-follow-up">
            <p>
                <span
                    className={
                        'status-chip ' +
                        (incident.priority === 'urgent' ? 'red' : 'gray')
                    }
                >
                    {
                        {
                            low: 'Faible',
                            normal: 'Normale',
                            high: 'Haute',
                            urgent: 'Urgente',
                        }[incident.priority ?? 'normal']
                    }
                </span>{' '}
                · {incident.responsible?.name ?? 'Responsable à désigner'}
            </p>
            {repair && (
                <p className="mt-3">
                    <Link href={dashboard({ query: { view: 'missions' } })}>
                        Intervention #{repair.id}
                    </Link>{' '}
                    ·{' '}
                    {repair.status === 'validated'
                        ? 'Compte rendu validé'
                        : repair.status === 'cancelled'
                          ? 'Annulée'
                          : 'En cours de suivi'}
                </p>
            )}
            <div className="operation-actions mt-4">
                {incident.status !== 'resolved' ? (
                    <>
                        <button
                            className="brand-button secondary small"
                            onClick={() =>
                                setMode(mode === 'assign' ? '' : 'assign')
                            }
                        >
                            Responsable et priorité
                        </button>
                        {(!repair || repair.status === 'cancelled') && (
                            <button
                                className="brand-button secondary small"
                                onClick={() =>
                                    setMode(mode === 'repair' ? '' : 'repair')
                                }
                            >
                                Planifier une correction
                            </button>
                        )}
                        <button
                            className="brand-button secondary small"
                            onClick={() =>
                                setMode(mode === 'resolve' ? '' : 'resolve')
                            }
                        >
                            Clôturer
                        </button>
                    </>
                ) : (
                    <button
                        className="brand-button secondary small"
                        onClick={() =>
                            setMode(mode === 'reopen' ? '' : 'reopen')
                        }
                    >
                        Rouvrir
                    </button>
                )}
            </div>
            {mode === 'assign' && (
                <BusinessForm
                    key={'assign' + incident.version}
                    action={incidentRoutes.followUp.url(incident.id)}
                    onSuccess={() => setMode('')}
                >
                    <input
                        name="version"
                        type="hidden"
                        value={incident.version}
                    />
                    <SelectField
                        label="Responsable du suivi"
                        name="responsible_user_id"
                        defaultValue={incident.responsible_user_id ?? ''}
                        items={staff}
                    />
                    <SelectField
                        label="Priorité"
                        name="priority"
                        required
                        empty=""
                        defaultValue={incident.priority}
                        items={[
                            { value: 'low', label: 'Faible' },
                            { value: 'normal', label: 'Normale' },
                            { value: 'high', label: 'Haute' },
                            { value: 'urgent', label: 'Urgente' },
                        ]}
                    />
                    <TextArea
                        label="Note interne pour l’historique"
                        name="note"
                    />
                </BusinessForm>
            )}
            {mode === 'repair' && (
                <BusinessForm
                    key={'repair' + incident.version}
                    action={incidentRoutes.repair.url(incident.id)}
                    onSuccess={() => setMode('')}
                >
                    <input
                        name="version"
                        type="hidden"
                        value={incident.version}
                    />
                    <SelectField
                        label="Modèle de correction"
                        name="template_id"
                        required
                        items={props.templates.map((t) => ({
                            value: t.id,
                            label: t.name,
                        }))}
                    />
                    <SelectField
                        label="Intervenant"
                        name="assigned_user_id"
                        items={staff}
                    />
                    <Field
                        label="Date et heure (Paris)"
                        name="scheduled_at"
                        type="datetime-local"
                        required
                    />
                    <Field
                        label="Durée (minutes)"
                        name="duration"
                        type="number"
                        min={5}
                        max={1440}
                        defaultValue={60}
                        required
                    />
                </BusinessForm>
            )}
            {mode === 'resolve' && (
                <BusinessForm
                    key={'resolve' + incident.version}
                    action={incidentRoutes.resolve.url(incident.id)}
                    onSuccess={() => setMode('')}
                    transform={(data) => {
                        const values = data as Record<string, unknown>;
                        return {
                            ...values,
                            resolution_photo_ids: Array.isArray(
                                values.resolution_photo_ids,
                            )
                                ? values.resolution_photo_ids.map(Number)
                                : [],
                        };
                    }}
                >
                    <input
                        name="version"
                        type="hidden"
                        value={incident.version}
                    />
                    <TextArea
                        label="Expliquez la résolution (interne)"
                        name="resolution_notes"
                        required
                    />
                    {repair && repair.status !== 'validated' && (
                        <p className="form-hint">
                            Le compte rendu de la correction doit être validé
                            avant clôture.
                        </p>
                    )}
                    {photos.map((photo) => (
                        <label className="admin-checkbox" key={photo.id}>
                            <input
                                name="resolution_photo_ids[]"
                                type="checkbox"
                                value={photo.id}
                            />{' '}
                            <img
                                className="resolution-photo-preview"
                                src={photoRoutes.show.url(photo.id)}
                                alt={`Preuve de résolution, photo ${photo.id}`}
                                loading="lazy"
                            />
                            Joindre la photo #{photo.id} à la résolution
                        </label>
                    ))}
                    <p className="form-hint">
                        La clôture reste interne jusqu’à une transmission
                        explicite au client.
                    </p>
                </BusinessForm>
            )}
            {mode === 'reopen' && (
                <BusinessForm
                    action={incidentRoutes.reopen.url(incident.id)}
                    onSuccess={() => setMode('')}
                >
                    <input
                        name="version"
                        type="hidden"
                        value={incident.version}
                    />
                    <TextArea
                        label="Motif de réouverture"
                        name="note"
                        required
                    />
                </BusinessForm>
            )}
            {incident.events?.length > 0 && (
                <details className="incident-history">
                    <summary>
                        Historique du dossier ({incident.events.length})
                    </summary>
                    {incident.events.map((event) => (
                        <div key={event.id}>
                            <b>
                                {event.user.name} · {when(event.created_at)}
                            </b>
                            <p>{event.description}</p>
                        </div>
                    ))}
                </details>
            )}
        </div>
    );
}

export function MonthlyReportButton({ props }: { props: WorkspaceProps }) {
    const [client, setClient] = useState(String(props.clients[0]?.id ?? ''));
    const [month, setMonth] = useState(() =>
        new Date()
            .toLocaleDateString('fr-CA', { timeZone: 'Europe/Paris' })
            .slice(0, 7),
    );
    return (
        <section className="workspace-card operation-settings mb-5">
            <h2>Bilan mensuel du client</h2>
            <div className="monthly-report-controls">
                <label className="form-field">
                    Client
                    <select
                        value={client}
                        onChange={(e) => setClient(e.target.value)}
                    >
                        {props.clients.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.name}
                            </option>
                        ))}
                    </select>
                </label>
                <label className="form-field">
                    Mois
                    <input
                        type="month"
                        value={month}
                        onChange={(e) => setMonth(e.target.value)}
                    />
                </label>
                {client && month && (
                    <a
                        className="brand-button small"
                        href={clientRoutes.bilan.url({
                            client: Number(client),
                            month,
                        })}
                    >
                        <Download size={15} />
                        Télécharger le PDF
                    </a>
                )}
            </div>
            <p className="form-hint">
                Le bilan contient uniquement les comptes rendus validés et les
                incidents partagés avec ce client.
            </p>
        </section>
    );
}

export function ReportPdfLink({ id }: { id: number }) {
    return (
        <a className="terrain-link" href={reportRoutes.pdf.url(id)}>
            <Download size={14} />
            Compte rendu PDF
        </a>
    );
}
