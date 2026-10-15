import { useState } from 'react';
import { CalendarDays, ChevronLeft, ChevronRight } from 'lucide-react';
import {
    BusinessForm,
    Field,
    SelectField,
} from '@/components/concierge/business-form';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { reschedule } from '@/routes/missions';
import type { MissionRecord, WorkspaceProps } from '@/types/concierge';

const parisDate = new Intl.DateTimeFormat('sv-SE', {
    timeZone: 'Europe/Paris',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
});
const parisTime = new Intl.DateTimeFormat('fr-FR', {
    timeZone: 'Europe/Paris',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
});
const dayLabel = new Intl.DateTimeFormat('fr-FR', {
    timeZone: 'UTC',
    weekday: 'short',
    day: 'numeric',
    month: 'short',
});
const statusLabels: Record<string, string> = {
    planned: 'Planifiée',
    accepted: 'Acceptée',
    in_progress: 'En cours',
    submitted: 'À valider',
    validated: 'Validée',
    changes_requested: 'À reprendre',
};

function addDays(day: string, count: number): string {
    const date = new Date(day + 'T12:00:00Z');
    date.setUTCDate(date.getUTCDate() + count);
    return date.toISOString().slice(0, 10);
}

function monday(day: string): string {
    const weekday = new Date(day + 'T12:00:00Z').getUTCDay();
    return addDays(day, -(weekday === 0 ? 6 : weekday - 1));
}

export function RescheduleMissionButton({
    mission,
    props,
}: {
    mission: MissionRecord;
    props: WorkspaceProps;
}) {
    const [open, setOpen] = useState(false);
    if (
        props.membership.role !== 'manager' ||
        !['planned', 'accepted'].includes(mission.status)
    )
        return null;
    const date = new Date(mission.scheduled_at);
    return (
        <>
            <button
                type="button"
                className="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-teal hover:underline"
                onClick={() => setOpen(true)}
                aria-label={`Replanifier ${mission.title}`}
            >
                <CalendarDays size={13} /> Déplacer / réaffecter
            </button>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="business-dialog">
                    <DialogHeader>
                        <DialogTitle>Replanifier · {mission.title}</DialogTitle>
                        <DialogDescription>
                            Choisissez le créneau et l’intervenant. La mission
                            devra être acceptée à nouveau après ce changement.
                        </DialogDescription>
                    </DialogHeader>
                    {open ? (
                        <BusinessForm
                            key={`${mission.id}-${mission.version}`}
                            action={reschedule.url(mission.id)}
                            onSuccess={() => setOpen(false)}
                            submit="Enregistrer le nouveau planning"
                        >
                            <input
                                type="hidden"
                                name="version"
                                value={mission.version}
                            />
                            <Field
                                label="Date et heure (Paris)"
                                name="scheduled_at"
                                type="datetime-local"
                                defaultValue={
                                    parisDate.format(date) +
                                    'T' +
                                    parisTime.format(date)
                                }
                                required
                            />
                            <Field
                                label="Durée en minutes"
                                name="duration"
                                type="number"
                                defaultValue={mission.duration}
                                min={5}
                                max={1440}
                                required
                            />
                            <SelectField
                                label="Intervenant"
                                name="assigned_user_id"
                                defaultValue={mission.assigned_user_id ?? ''}
                                empty="À affecter"
                                items={props.members
                                    .filter((member) =>
                                        ['manager', 'worker'].includes(
                                            member.role,
                                        ),
                                    )
                                    .map((member) => ({
                                        value: member.user_id,
                                        label: member.user.name,
                                    }))}
                            />
                            {mission.assigned_company ? (
                                <p className="form-hint">
                                    Cette mission est confiée à{' '}
                                    {mission.assigned_company}. Choisissez une
                                    personne pour reprendre son affectation.
                                </p>
                            ) : null}
                            <p className="form-hint">
                                Les indisponibilités et les chevauchements sont
                                vérifiés avant l’enregistrement.
                            </p>
                        </BusinessForm>
                    ) : null}
                </DialogContent>
            </Dialog>
        </>
    );
}

export function TeamPlanning({
    props,
    onMission,
}: {
    props: WorkspaceProps;
    onMission: (mission: MissionRecord) => void;
}) {
    const [week, setWeek] = useState(() =>
        monday(parisDate.format(new Date())),
    );
    const [property, setProperty] = useState('');
    const [person, setPerson] = useState('');
    const [status, setStatus] = useState('');
    const days = Array.from({ length: 7 }, (_, index) => addDays(week, index));
    const today = parisDate.format(new Date());
    const people = props.members.filter((member) =>
        ['manager', 'worker'].includes(member.role),
    );
    const lanes = [
        ...people.map((member) => ({
            id: String(member.user_id),
            name: member.user.name,
            hint: member.company ?? member.team ?? 'Équipe interne',
        })),
        {
            id: 'unassigned',
            name: 'À affecter / prestataires',
            hint: 'Missions sans intervenant désigné',
        },
    ].filter((lane) => !person || lane.id === person);
    const missions = props.missions.filter(
        (mission) =>
            mission.status !== 'cancelled' &&
            (!property || mission.property_id === Number(property)) &&
            (!status || mission.status === status),
    );
    const buckets = new Map<string, MissionRecord[]>();
    for (const mission of missions) {
        const start = new Date(mission.scheduled_at);
        const firstDay = parisDate.format(start);
        const lastDay = parisDate.format(
            new Date(start.getTime() + mission.duration * 60000 - 1),
        );
        const lane =
            mission.assigned_user_id === null
                ? 'unassigned'
                : String(mission.assigned_user_id);
        for (const day of days) {
            if (firstDay <= day && lastDay >= day) {
                const key = lane + ':' + day;
                const list = buckets.get(key) ?? [];
                list.push(mission);
                buckets.set(key, list);
            }
        }
    }
    return (
        <section
            className="workspace-card mb-6"
            aria-labelledby="team-planning-heading"
        >
            <div className="workspace-card-heading flex-wrap gap-3">
                <div>
                    <h2 id="team-planning-heading">Planning par intervenant</h2>
                    <p className="mt-1 text-xs text-slate-500">
                        Semaine du{' '}
                        {dayLabel.format(new Date(week + 'T12:00:00Z'))} ·
                        horaires de Paris
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        aria-label="Semaine précédente"
                        onClick={() => setWeek(addDays(week, -7))}
                    >
                        <ChevronLeft size={18} />
                    </button>
                    <button
                        type="button"
                        className="text-xs font-semibold text-teal"
                        onClick={() => setWeek(monday(today))}
                    >
                        Aujourd’hui
                    </button>
                    <button
                        type="button"
                        aria-label="Semaine suivante"
                        onClick={() => setWeek(addDays(week, 7))}
                    >
                        <ChevronRight size={18} />
                    </button>
                    <label className="form-field">
                        <span className="sr-only">Choisir une semaine</span>
                        <input
                            type="date"
                            aria-label="Choisir une semaine"
                            value={week}
                            onChange={(event) => {
                                if (event.target.value)
                                    setWeek(monday(event.target.value));
                            }}
                        />
                    </label>
                </div>
            </div>
            <div className="grid gap-3 p-5 sm:grid-cols-3">
                <label className="form-field">
                    Lieu
                    <select
                        value={property}
                        onChange={(event) => setProperty(event.target.value)}
                    >
                        <option value="">Tous les lieux</option>
                        {props.properties.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.name}
                            </option>
                        ))}
                    </select>
                </label>
                <label className="form-field">
                    Intervenant
                    <select
                        value={person}
                        onChange={(event) => setPerson(event.target.value)}
                    >
                        <option value="">Toute l’équipe</option>
                        {lanesForFilter(people).map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.name}
                            </option>
                        ))}
                    </select>
                </label>
                <label className="form-field">
                    Statut
                    <select
                        value={status}
                        onChange={(event) => setStatus(event.target.value)}
                    >
                        <option value="">Tous les statuts actifs</option>
                        {Object.entries(statusLabels).map(([value, label]) => (
                            <option key={value} value={value}>
                                {label}
                            </option>
                        ))}
                    </select>
                </label>
            </div>
            <div
                className="mx-5 overflow-x-auto rounded-xl border border-slate-200"
                tabIndex={0}
                role="region"
                aria-label="Planning hebdomadaire de l’équipe"
            >
                <table className="w-full min-w-[1100px] table-fixed border-collapse text-left text-xs">
                    <thead>
                        <tr className="bg-slate-50">
                            <th scope="col" className="w-44 p-3">
                                Intervenant
                            </th>
                            {days.map((day) => (
                                <th
                                    scope="col"
                                    key={day}
                                    className={`border-l border-slate-200 p-3 ${day === today ? 'bg-teal/10 text-teal' : ''}`}
                                >
                                    {dayLabel.format(
                                        new Date(day + 'T12:00:00Z'),
                                    )}
                                    {day === today ? (
                                        <span className="mt-1 block text-[10px]">
                                            Aujourd’hui
                                        </span>
                                    ) : null}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {lanes.map((lane) => (
                            <tr key={lane.id}>
                                <th
                                    scope="row"
                                    className="border-t border-slate-200 p-3 align-top"
                                >
                                    <span className="block font-semibold">
                                        {lane.name}
                                    </span>
                                    <span className="mt-1 block font-normal text-slate-500">
                                        {lane.hint}
                                    </span>
                                </th>
                                {days.map((day) => {
                                    const items =
                                        buckets.get(lane.id + ':' + day) ?? [];
                                    return (
                                        <td
                                            key={day}
                                            className={`border-t border-l border-slate-200 p-2 align-top ${day === today ? 'bg-teal/5' : ''}`}
                                        >
                                            {items.length ? (
                                                <div className="grid gap-2">
                                                    {items.map((mission) => (
                                                        <div
                                                            key={mission.id}
                                                            className="rounded-lg border border-slate-200 bg-white p-2"
                                                        >
                                                            <button
                                                                type="button"
                                                                className="w-full text-left"
                                                                onClick={() =>
                                                                    onMission(
                                                                        mission,
                                                                    )
                                                                }
                                                            >
                                                                <span className="block font-semibold text-teal">
                                                                    {parisDate.format(
                                                                        new Date(
                                                                            mission.scheduled_at,
                                                                        ),
                                                                    ) < day
                                                                        ? 'Depuis la veille'
                                                                        : parisTime.format(
                                                                              new Date(
                                                                                  mission.scheduled_at,
                                                                              ),
                                                                          )}{' '}
                                                                    ·{' '}
                                                                    {
                                                                        mission.duration
                                                                    }{' '}
                                                                    min
                                                                </span>
                                                                <span className="mt-1 block font-semibold">
                                                                    {
                                                                        mission.title
                                                                    }
                                                                </span>
                                                                <span className="mt-1 block text-slate-500">
                                                                    {
                                                                        mission
                                                                            .property
                                                                            .name
                                                                    }
                                                                </span>
                                                                <span className="mt-2 block text-[10px] text-slate-600">
                                                                    {statusLabels[
                                                                        mission
                                                                            .status
                                                                    ] ??
                                                                        mission.status}
                                                                    {mission.assigned_company
                                                                        ? ' · ' +
                                                                          mission.assigned_company
                                                                        : ''}
                                                                </span>
                                                            </button>
                                                            <RescheduleMissionButton
                                                                mission={
                                                                    mission
                                                                }
                                                                props={props}
                                                            />
                                                        </div>
                                                    ))}
                                                </div>
                                            ) : (
                                                <span
                                                    className="block py-3 text-center text-slate-400"
                                                    aria-label="Aucune mission"
                                                >
                                                    —
                                                </span>
                                            )}
                                        </td>
                                    );
                                })}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <p className="form-hint p-5">
                Cliquez sur une mission pour ouvrir son dossier, ou sur «
                Déplacer / réaffecter » pour modifier son créneau.
            </p>
        </section>
    );
}

function lanesForFilter(people: WorkspaceProps['members']) {
    return [
        ...people.map((member) => ({
            id: String(member.user_id),
            name: member.user.name,
        })),
        { id: 'unassigned', name: 'À affecter / prestataires' },
    ];
}
