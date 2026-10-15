import { Link } from '@inertiajs/react';
import { CheckCheck, TriangleAlert } from 'lucide-react';
import { dashboard } from '@/routes';
import type { MissionRecord, WorkspaceProps } from '@/types/concierge';

const groups = [
    {
        key: 'urgent_incidents',
        title: 'Incidents prioritaires',
        hint: 'Priorité haute ou urgente',
        tone: 'red',
    },
    {
        key: 'late_missions',
        title: 'Démarrages en retard',
        hint: 'L’heure prévue est dépassée',
        tone: 'red',
    },
    {
        key: 'unassigned_missions',
        title: 'Missions sans intervenant',
        hint: 'À affecter pour les prochaines 24 h ou en retard',
        tone: 'amber',
    },
    {
        key: 'pending_missions',
        title: 'Acceptations attendues',
        hint: 'À confirmer pour les prochaines 24 h ou en retard',
        tone: 'amber',
    },
    {
        key: 'reports',
        title: 'Comptes rendus à valider',
        hint: 'Une décision du responsable est attendue',
        tone: 'blue',
    },
] as const;

export function ActionCenter({
    props,
    onMission,
}: {
    props: WorkspaceProps;
    onMission: (mission: MissionRecord) => void;
}) {
    if (!props.actionCenter) return null;
    const active = groups.filter(
        (group) => props.actionCenter![group.key].length > 0,
    );
    const missions = new Map(
        props.missions.map((mission) => [mission.id, mission]),
    );
    const incidents = new Map(
        props.incidents.map((incident) => [incident.id, incident]),
    );
    return (
        <section
            className="workspace-card mb-6"
            aria-labelledby="action-center-heading"
        >
            <div className="workspace-card-heading">
                <h2
                    id="action-center-heading"
                    className="flex items-center gap-2"
                >
                    <TriangleAlert size={18} /> À traiter maintenant
                </h2>
                <span className="text-xs text-slate-500">
                    Priorités de votre conciergerie
                </span>
            </div>
            {active.length ? (
                <div className="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-3">
                    {active.map((group) => {
                        const ids = props.actionCenter![group.key];
                        return (
                            <div
                                key={group.key}
                                className="rounded-xl border border-slate-200 p-4"
                            >
                                <div className="flex items-center justify-between gap-2">
                                    <h3 className="text-sm font-semibold">
                                        {group.title}
                                    </h3>
                                    <span
                                        className={`status-chip ${group.tone}`}
                                    >
                                        {ids.length}
                                    </span>
                                </div>
                                <p className="mt-1 text-xs text-slate-500">
                                    {group.hint}
                                </p>
                                <ul className="mt-3 grid gap-2">
                                    {ids.slice(0, 3).map((id) => {
                                        if (group.key === 'urgent_incidents') {
                                            const incident = incidents.get(id);
                                            return incident ? (
                                                <li key={id}>
                                                    <Link
                                                        className="text-xs font-semibold text-teal hover:underline"
                                                        href={
                                                            dashboard.url({
                                                                query: {
                                                                    view: 'incidents',
                                                                },
                                                            }) +
                                                            '#incident-' +
                                                            id
                                                        }
                                                    >
                                                        {
                                                            incident.mission
                                                                .property.name
                                                        }{' '}
                                                        · incident #{id} →
                                                    </Link>
                                                </li>
                                            ) : null;
                                        }
                                        const mission = missions.get(id);
                                        return mission ? (
                                            <li key={id}>
                                                <button
                                                    type="button"
                                                    className="text-left text-xs font-semibold text-teal hover:underline"
                                                    onClick={() =>
                                                        onMission(mission)
                                                    }
                                                >
                                                    {mission.title} ·{' '}
                                                    {mission.property.name} →
                                                </button>
                                            </li>
                                        ) : null;
                                    })}
                                </ul>
                                {ids.length > 3 ? (
                                    <Link
                                        className="mt-3 inline-block text-xs font-semibold text-teal"
                                        href={dashboard({
                                            query: {
                                                view:
                                                    group.key ===
                                                    'urgent_incidents'
                                                        ? 'incidents'
                                                        : group.key ===
                                                            'reports'
                                                          ? 'reports'
                                                          : 'missions',
                                            },
                                        })}
                                    >
                                        Voir la liste complète →
                                    </Link>
                                ) : null}
                            </div>
                        );
                    })}
                </div>
            ) : (
                <p className="flex items-center gap-2 p-5 text-sm text-teal">
                    <CheckCheck size={18} /> Tout est à jour. Aucune action
                    prioritaire en attente.
                </p>
            )}
        </section>
    );
}
