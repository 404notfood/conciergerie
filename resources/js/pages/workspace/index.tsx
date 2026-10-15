import {
    NotificationBell,
    NotificationsPanel,
    RecurrencesPanel,
    AvailabilityPanel,
    IncidentFollowUp,
    MonthlyReportButton,
    ReportPdfLink,
} from '@/components/concierge/operations';
import { ActionCenter } from '@/components/concierge/action-center';
import {
    ArchiveFilter,
    DirectoryActions,
} from '@/components/concierge/directory-management';
import {
    RescheduleMissionButton,
    TeamPlanning,
} from '@/components/concierge/team-planning';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Building2,
    CalendarDays,
    Camera,
    Check,
    CheckCheck,
    ChevronLeft,
    ChevronRight,
    ClipboardList,
    FileCheck2,
    FileText,
    Home,
    House,
    LogOut,
    MessageSquare,
    Plus,
    RefreshCw,
    Search,
    Settings,
    TriangleAlert,
    Users,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { InertiaBrand as Brand } from '@/components/concierge/brand';
import {
    BusinessForm,
    Field,
    operationId,
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
import { dashboard, logout } from '@/routes';
import { show as showSubscription } from '@/routes/subscription';
import { leave as leaveAdministration } from '@/routes/admin';
import * as clientRoutes from '@/routes/clients';
import * as propertyRoutes from '@/routes/properties';
import * as memberRoutes from '@/routes/members';
import * as templateRoutes from '@/routes/templates';
import * as stayRoutes from '@/routes/stays';
import * as missionRoutes from '@/routes/missions';
import * as reportRoutes from '@/routes/reports';
import * as incidentRoutes from '@/routes/incidents';
import * as messageRoutes from '@/routes/messages';
import * as requestRoutes from '@/routes/requests';
import * as photoRoutes from '@/routes/photos';
import { edit as editProfile } from '@/routes/profile';
import type {
    WorkspaceProps,
    MissionRecord,
    IncidentRecord,
    RequestRecord,
} from '@/types/concierge';
const statusInfo: Record<string, { label: string; tone: string }> = {
    planned: { label: 'Planifiée', tone: 'blue' },
    accepted: { label: 'Acceptée', tone: 'blue' },
    in_progress: { label: 'En cours', tone: 'green' },
    submitted: { label: 'À valider', tone: 'amber' },
    validated: { label: 'Validée', tone: 'green' },
    changes_requested: { label: 'À reprendre', tone: 'red' },
    cancelled: { label: 'Annulée', tone: 'gray' },
    pending: { label: 'En attente', tone: 'amber' },
    rejected: { label: 'Refusée', tone: 'gray' },
    open: { label: 'À traiter', tone: 'red' },
    resolved: { label: 'Résolu', tone: 'green' },
};
const viewNames: Record<string, string> = {
    overview: 'Vue d’ensemble',
    missions: 'Missions',
    planning: 'Planning',
    team: 'Équipes',
    clients: 'Clients',
    properties: 'Lieux d’intervention',
    reports: 'Comptes rendus',
    incidents: 'Incidents',
    notifications: 'Notifications',
    recurrences: 'Récurrences',
    availability: 'Disponibilités',
    requests: 'Demandes',
    messages: 'Messagerie',
    templates: 'Prestations',
};
const roleNames = {
    manager: 'Responsable',
    worker: 'Intervenant',
    client: 'Client',
    coordinator: 'Responsable prestataire',
};
const propertyKinds = [
    { value: 'rental', label: 'Location saisonnière' },
    { value: 'residence', label: 'Résidence' },
    { value: 'business', label: 'Entreprise' },
    { value: 'other', label: 'Autre lieu' },
];
function Status({ status }: { status: string }) {
    const info = statusInfo[status] ?? { label: status, tone: 'gray' };
    return (
        <span className={'status-chip ' + info.tone}>
            {status === 'validated' ? <Check size={11} /> : null}
            {info.label}
        </span>
    );
}
function initials(name: string) {
    return name
        .split(' ')
        .map((part) => part[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
}
function dateLabel(value: string, includeTime = false) {
    return new Date(value).toLocaleString(
        'fr-FR',
        includeTime
            ? {
                  day: 'numeric',
                  month: 'short',
                  hour: '2-digit',
                  minute: '2-digit',
              }
            : { day: 'numeric', month: 'short', year: 'numeric' },
    );
}
function localDay(date: Date) {
    return (
        date.getFullYear() +
        '-' +
        String(date.getMonth() + 1).padStart(2, '0') +
        '-' +
        String(date.getDate()).padStart(2, '0')
    );
}
function Empty({
    title,
    text,
    action,
}: {
    title: string;
    text: string;
    action?: { label: string; onClick: () => void };
}) {
    return (
        <div className="empty-state">
            <ClipboardList />
            <b>{title}</b>
            <p>{text}</p>
            {action ? (
                <button className="brand-button small" onClick={action.onClick}>
                    {action.label}
                </button>
            ) : null}
        </div>
    );
}
function MissionRow({
    mission,
    onClick,
}: {
    mission: MissionRecord;
    onClick: () => void;
}) {
    const when = new Date(mission.scheduled_at);
    return (
        <button className="mission-row" onClick={onClick}>
            <span className="mission-time">
                {when.toLocaleTimeString('fr-FR', {
                    hour: '2-digit',
                    minute: '2-digit',
                })}
                <small>
                    {when.toLocaleDateString('fr-FR', {
                        day: '2-digit',
                        month: '2-digit',
                    })}
                </small>
            </span>
            <span className="mission-info">
                <b>{mission.title}</b>
                <small>
                    {mission.property.name} · {mission.duration} min
                </small>
            </span>
            {mission.assignee ? (
                <span className="user-avatar" title={mission.assignee.name}>
                    {initials(mission.assignee.name)}
                </span>
            ) : (
                <span className="user-avatar" title="À affecter">
                    —
                </span>
            )}
            <Status status={mission.status} />
            <ChevronRight size={14} />
        </button>
    );
}
function Calendar({
    missions,
    selected,
    onSelect,
}: {
    missions: MissionRecord[];
    selected: string;
    onSelect: (date: string) => void;
}) {
    const [month, setMonth] = useState(() => new Date());
    const first = new Date(month.getFullYear(), month.getMonth(), 1);
    const offset = (first.getDay() + 6) % 7;
    const length = new Date(
        month.getFullYear(),
        month.getMonth() + 1,
        0,
    ).getDate();
    return (
        <div className="workspace-calendar">
            <div className="workspace-calendar-head">
                <span className="capitalize">
                    {month.toLocaleDateString('fr-FR', {
                        month: 'long',
                        year: 'numeric',
                    })}
                </span>
                <div>
                    <button
                        aria-label="Mois précédent"
                        onClick={() =>
                            setMonth(
                                new Date(
                                    month.getFullYear(),
                                    month.getMonth() - 1,
                                    1,
                                ),
                            )
                        }
                    >
                        <ChevronLeft size={15} />
                    </button>
                    <button
                        aria-label="Mois suivant"
                        onClick={() =>
                            setMonth(
                                new Date(
                                    month.getFullYear(),
                                    month.getMonth() + 1,
                                    1,
                                ),
                            )
                        }
                    >
                        <ChevronRight size={15} />
                    </button>
                </div>
            </div>
            <div className="calendar-grid">
                {['L', 'Ma', 'Me', 'J', 'V', 'S', 'D'].map((d, i) => (
                    <small key={i}>{d}</small>
                ))}
                {Array.from(
                    { length: Math.ceil((offset + length) / 7) * 7 },
                    (_, i) => {
                        const day = i - offset + 1;
                        const date = localDay(
                            new Date(
                                month.getFullYear(),
                                month.getMonth(),
                                day,
                            ),
                        );
                        return (
                            <span key={i}>
                                {day > 0 && day <= length ? (
                                    <button
                                        aria-label={dateLabel(date)}
                                        className={
                                            'calendar-day ' +
                                            (selected === date
                                                ? 'active '
                                                : '') +
                                            (missions.some(
                                                (m) =>
                                                    localDay(
                                                        new Date(
                                                            m.scheduled_at,
                                                        ),
                                                    ) === date,
                                            )
                                                ? 'has-mission'
                                                : '')
                                        }
                                        onClick={() => onSelect(date)}
                                    >
                                        {day}
                                    </button>
                                ) : null}
                            </span>
                        );
                    },
                )}
            </div>
        </div>
    );
}
export default function Workspace(props: WorkspaceProps) {
    const { auth, success, name } = usePage<{ success?: string }>().props;
    const { url } = usePage();
    const rawView =
        new URL(url, 'http://local').searchParams.get('view') ?? 'overview';
    const view = Object.hasOwn(viewNames, rawView) ? rawView : 'overview';
    const isManager = props.membership.role === 'manager';
    const isClient = props.membership.role === 'client';
    const [modal, setModal] = useState<string | null>(null);
    const [selectedMissionId, setSelectedMissionId] = useState<number | null>(
        null,
    );
    const [selectedIncident, setSelectedIncident] =
        useState<IncidentRecord | null>(null);
    const [selectedRequest, setSelectedRequest] =
        useState<RequestRecord | null>(null);
    const [showArchived, setShowArchived] = useState(false);
    const visibleClients = props.clients.filter(
        (record) => showArchived || !record.archived_at,
    );
    const visibleProperties = props.properties.filter(
        (record) => showArchived || !record.archived_at,
    );
    const visibleTemplates = props.templates.filter(
        (record) => showArchived || !record.archived_at,
    );
    const visibleStays = props.stays.filter(
        (record) => showArchived || !record.archived_at,
    );
    const [search, setSearch] = useState('');
    const [selectedDay, setSelectedDay] = useState(() => localDay(new Date()));
    const [contact, setContact] = useState<number | null>(
        () => props.clients[0]?.id ?? null,
    );
    const [memberRole, setMemberRole] = useState('worker');
    const [assignmentMode, setAssignmentMode] = useState('individual');
    const [requestDecision, setRequestDecision] = useState('accepted');
    const [receiptId, setReceiptId] = useState('');
    const selectedMission = props.missions.find(
        (m) => m.id === selectedMissionId,
    );
    const currentReport = selectedMission?.reports[0];
    const reportPhotos = [
        ...(selectedMission?.reports.flatMap((r) => r.photos) ?? []),
        ...(props.missions
            .find((m) => m.id === selectedIncident?.repair_mission_id)
            ?.reports.filter((r) => r.status === 'validated')
            .flatMap((r) => r.photos) ?? []),
    ];
    useEffect(() => {
        if (view === 'messages' && !isClient) {
            const timer = setInterval(
                () => router.reload({ only: ['messages'] }),
                15000,
            );
            return () => clearInterval(timer);
        }
        if (view === 'messages' && isClient) {
            const timer = setInterval(
                () => router.reload({ only: ['messages'] }),
                15000,
            );
            return () => clearInterval(timer);
        }
    }, [view, isClient]);
    function open(kind: string) {
        setReceiptId(operationId());
        setModal(kind);
    }
    function close() {
        setModal(null);
        setSelectedMissionId(null);
        setSelectedIncident(null);
        setSelectedRequest(null);
    }
    function showMission(mission: MissionRecord) {
        setSelectedMissionId(mission.id);
        open('mission-detail');
    }
    const missionList = props.missions.filter((m) =>
        (m.title + ' ' + m.property.name + ' ' + (m.assignee?.name ?? ''))
            .toLowerCase()
            .includes(search.toLowerCase()),
    );
    const dayMissions = props.missions.filter(
        (m) =>
            localDay(new Date(m.scheduled_at)) === selectedDay &&
            m.status !== 'cancelled',
    );
    const nav = [
        { key: 'overview', icon: Home },
        { key: 'missions', icon: ClipboardList },
        { key: 'planning', icon: CalendarDays },
        { key: 'recurrences', icon: CalendarDays },
        { key: 'availability', icon: Users },
        { key: 'notifications', icon: FileCheck2 },
        { key: 'team', icon: Users },
        { key: 'clients', icon: Users },
        { key: 'properties', icon: House },
        { key: 'reports', icon: FileText },
        { key: 'incidents', icon: TriangleAlert },
        { key: 'requests', icon: FileCheck2 },
        { key: 'messages', icon: MessageSquare },
        { key: 'templates', icon: Settings },
    ].filter(
        (item) =>
            isManager ||
            (!isClient &&
                [
                    'overview',
                    'missions',
                    'reports',
                    'incidents',
                    'availability',
                    'notifications',
                ].includes(item.key)) ||
            (isClient &&
                [
                    'overview',
                    'notifications',
                    'properties',
                    'reports',
                    'incidents',
                    'requests',
                    'messages',
                ].includes(item.key)),
    );
    const propertyOptions = props.properties
        .filter((p) => !p.archived_at)
        .map((p) => ({
            value: p.id,
            label: p.name,
        }));
    const assigneeOptions = props.members
        .filter((m) => m.role === 'manager' || m.role === 'worker')
        .map((m) => ({
            value: m.user_id,
            label: m.user.name + (m.company ? ' · ' + m.company : ''),
        }));
    const templateOptions = props.templates
        .filter((t) => !t.archived_at)
        .map((t) => ({
            value: t.id,
            label: t.name,
        }));
    const clientOptions = props.clients
        .filter((c) => !c.archived_at)
        .map((c) => ({
            value: c.id,
            label: c.name,
        }));
    const newAction: Record<string, { label: string; modal: string }> = {
        missions: { label: 'Nouvelle mission', modal: 'new-mission' },
        planning: { label: 'Nouvelle période', modal: 'new-stay' },
        clients: { label: 'Ajouter un client', modal: 'new-client' },
        properties: { label: 'Ajouter un lieu', modal: 'new-property' },
        team: { label: 'Ajouter une personne', modal: 'new-member' },
        templates: { label: 'Nouvelle prestation', modal: 'new-template' },
    };
    const modalNames: Record<string, string> = {
        'new-client': 'Ajouter un client',
        'new-property': 'Ajouter un lieu d’intervention',
        'new-member': 'Créer un compte individuel',
        'new-template': 'Créer une prestation',
        'new-mission': 'Planifier une mission',
        'new-stay': 'Ajouter une période',
        import: 'Importer un calendrier iCal',
        'mission-detail': selectedMission?.title ?? 'Mission',
        report: 'Soumettre le compte rendu',
        review: 'Contrôler le compte rendu',
        incident: 'Signaler un incident',
        'share-incident': 'Transmettre au client',
        'decide-request': 'Traiter la demande',
        'new-request': 'Demander une intervention',
        reassign: 'Réaffecter la mission',
        designate: 'Désigner l’intervenant de votre entreprise',
    };
    return (
        <div className="workspace">
            <Head title={viewNames[view]} />
            <aside className="workspace-sidebar">
                <Brand />
                <div className="workspace-switch">
                    <small>Votre conciergerie</small>
                    <b>{props.concierge.name}</b>
                </div>
                <nav className="workspace-nav" aria-label="Navigation métier">
                    {nav.map(({ key, icon: Icon }) => (
                        <Link
                            key={key}
                            href={dashboard({ query: { view: key } })}
                            className={view === key ? 'active' : ''}
                        >
                            <Icon size={18} />
                            {viewNames[key]}
                            {key === 'reports' &&
                            props.missions.some(
                                (m) => m.status === 'submitted',
                            ) ? (
                                <span className="nav-count">
                                    {
                                        props.missions.filter(
                                            (m) => m.status === 'submitted',
                                        ).length
                                    }
                                </span>
                            ) : null}
                            {key === 'requests' &&
                            props.requests.some(
                                (r) => r.status === 'pending',
                            ) ? (
                                <span className="nav-count">
                                    {
                                        props.requests.filter(
                                            (r) => r.status === 'pending',
                                        ).length
                                    }
                                </span>
                            ) : null}
                        </Link>
                    ))}
                </nav>
                <div className="workspace-sidebar-footer">
                    {!isClient ? (
                        <div className="terrain-nav-card">
                            <div>
                                <DownloadIcon /> Votre journée sur le terrain
                            </div>
                            <p>
                                Préparez vos missions acceptées et emportez
                                votre travail.
                            </p>
                            <a href="/terrain.html">
                                Ouvrir l’espace terrain →
                            </a>
                        </div>
                    ) : null}
                    <span>
                        {isManager ? (
                            <Link href={showSubscription()}>
                                Mon abonnement →
                            </Link>
                        ) : null}
                    </span>
                    <span>{name} · Votre espace privé</span>
                </div>
            </aside>
            <div className="workspace-main">
                <header className="workspace-topbar">
                    <span>
                        {props.concierge.name}{' '}
                        <ChevronRight className="mx-2 inline" size={11} />{' '}
                        {viewNames[view]}
                    </span>
                    <div>
                        <NotificationBell
                            inbox={props.notificationInbox}
                            identity={props.concierge.id + ':' + auth.user.id}
                        />
                        <div className="user-avatar">
                            {initials(auth.user.name)}
                        </div>
                        <Link href={editProfile()} className="user-identity">
                            <b>{auth.user.name}</b>
                            <small>{roleNames[props.membership.role]}</small>
                        </Link>
                        <Link
                            href={logout()}
                            as="button"
                            aria-label="Se déconnecter"
                        >
                            <LogOut size={16} />
                        </Link>
                    </div>
                </header>
                <main className="workspace-content">
                    {auth.user.is_admin === true ? (
                        <div className="notice admin-visit-notice">
                            <ShieldAdminIcon />
                            Vous consultez cet espace en tant qu’administrateur
                            global.
                            <Link
                                href={leaveAdministration()}
                                as="button"
                                className="ml-auto font-semibold"
                            >
                                Retour à l’administration
                            </Link>
                        </div>
                    ) : null}
                    {success ? (
                        <div className="notice" role="status">
                            <CheckCheck size={17} />
                            {success}
                        </div>
                    ) : null}
                    <div className="workspace-page-heading">
                        <div>
                            <h1>{viewNames[view]}</h1>
                            <p>
                                {view === 'overview'
                                    ? new Date().toLocaleDateString('fr-FR', {
                                          weekday: 'long',
                                          day: 'numeric',
                                          month: 'long',
                                          year: 'numeric',
                                      })
                                    : view === 'messages'
                                      ? 'Un échange direct avec votre conciergerie et vos clients.'
                                      : view === 'reports'
                                        ? 'Des preuves, un contrôle, puis un partage clair.'
                                        : view === 'recurrences'
                                          ? 'Préparez vos passages réguliers et confirmez chaque intervention.'
                                          : view === 'availability'
                                            ? 'Anticipez les absences et les conflits de planning.'
                                            : view === 'notifications'
                                              ? 'Suivez les actions attendues et choisissez vos alertes.'
                                              : 'Retrouvez et organisez les informations de votre conciergerie.'}
                            </p>
                        </div>
                        <div className="workspace-toolbar">
                            {view === 'planning' && isManager ? (
                                <button
                                    className="brand-button small secondary"
                                    onClick={() => open('import')}
                                >
                                    Importer iCal
                                </button>
                            ) : null}
                            {isManager &&
                            (newAction[view] || view === 'overview') ? (
                                <button
                                    className="brand-button small"
                                    onClick={() =>
                                        open(
                                            newAction[view]?.modal ??
                                                'new-mission',
                                        )
                                    }
                                >
                                    <Plus size={15} />
                                    {newAction[view]?.label ??
                                        'Nouvelle mission'}
                                </button>
                            ) : null}
                            {isClient && view === 'requests' ? (
                                <button
                                    className="brand-button small"
                                    onClick={() => open('new-request')}
                                >
                                    <Plus size={15} />
                                    Nouvelle demande
                                </button>
                            ) : null}
                            <button
                                aria-label="Actualiser les données"
                                onClick={() => router.reload()}
                            >
                                <RefreshCw size={16} />
                            </button>
                        </div>
                    </div>
                    {isManager &&
                    ['clients', 'properties', 'templates', 'planning'].includes(
                        view,
                    ) ? (
                        <ArchiveFilter
                            archived={showArchived}
                            onChange={setShowArchived}
                        />
                    ) : null}
                    {view === 'overview' && isManager ? (
                        <ActionCenter props={props} onMission={showMission} />
                    ) : null}
                    {view === 'planning' && isManager ? (
                        <TeamPlanning props={props} onMission={showMission} />
                    ) : null}
                    {view === 'overview' ? (
                        <>
                            <div className="stat-grid">
                                {[
                                    {
                                        label: isClient
                                            ? 'Comptes rendus partagés'
                                            : 'Missions du jour',
                                        count: isClient
                                            ? props.missions.length
                                            : dayMissions.length,
                                        icon: CalendarDays,
                                        hint: isClient
                                            ? 'Validés par votre conciergerie'
                                            : 'Votre programme',
                                        key: isClient ? 'reports' : 'missions',
                                    },
                                    {
                                        label: isClient
                                            ? 'Demandes en attente'
                                            : 'À valider',
                                        count: isClient
                                            ? props.requests.filter(
                                                  (r) => r.status === 'pending',
                                              ).length
                                            : props.missions.filter(
                                                  (m) =>
                                                      m.status === 'submitted',
                                              ).length,
                                        icon: FileCheck2,
                                        hint: 'Une action explicite',
                                        key: isClient ? 'requests' : 'reports',
                                    },
                                    {
                                        label: 'Incidents',
                                        count: props.incidents.filter(
                                            (i) => i.status === 'open',
                                        ).length,
                                        icon: TriangleAlert,
                                        hint: isClient
                                            ? 'Informations transmises'
                                            : 'À traiter par le responsable',
                                        key: 'incidents',
                                    },
                                    {
                                        label: isClient
                                            ? 'Mes lieux'
                                            : 'Équipe active',
                                        count: isClient
                                            ? props.properties.length
                                            : props.members.filter(
                                                  (m) => m.role !== 'client',
                                              ).length,
                                        icon: isClient ? House : Users,
                                        hint: isClient
                                            ? 'Votre patrimoine suivi'
                                            : 'Comptes individuels',
                                        key: isClient ? 'properties' : 'team',
                                    },
                                ]
                                    .filter(
                                        (s) =>
                                            isManager ||
                                            isClient ||
                                            s.key !== 'team',
                                    )
                                    .map(
                                        ({
                                            label,
                                            count,
                                            icon: Icon,
                                            hint,
                                            key,
                                        }) => (
                                            <Link
                                                className={
                                                    'workspace-card stat-card ' +
                                                    (key === 'incidents'
                                                        ? 'alert'
                                                        : '')
                                                }
                                                href={dashboard({
                                                    query: { view: key },
                                                })}
                                                key={key}
                                            >
                                                <span className="stat-icon">
                                                    <Icon size={21} />
                                                </span>
                                                <div>
                                                    <small>{label}</small>
                                                    <b>{count}</b>
                                                    <span>{hint}</span>
                                                </div>
                                            </Link>
                                        ),
                                    )}
                            </div>
                            <div className="dashboard-grid">
                                <div className="dashboard-primary">
                                    <section className="workspace-card">
                                        <div className="workspace-card-heading">
                                            <h2>
                                                {isClient
                                                    ? 'Derniers comptes rendus'
                                                    : selectedDay ===
                                                        localDay(new Date())
                                                      ? 'Missions du jour'
                                                      : 'Missions du ' +
                                                        dateLabel(selectedDay)}
                                            </h2>
                                            <Link
                                                href={dashboard({
                                                    query: {
                                                        view: isClient
                                                            ? 'reports'
                                                            : 'missions',
                                                    },
                                                })}
                                            >
                                                Voir tout →
                                            </Link>
                                        </div>
                                        {(isClient
                                            ? props.missions.slice(0, 5)
                                            : dayMissions
                                        ).length ? (
                                            (isClient
                                                ? props.missions.slice(0, 5)
                                                : dayMissions
                                            ).map((m) => (
                                                <MissionRow
                                                    mission={m}
                                                    key={m.id}
                                                    onClick={() =>
                                                        showMission(m)
                                                    }
                                                />
                                            ))
                                        ) : (
                                            <Empty
                                                title={
                                                    isClient
                                                        ? 'Votre suivi commence ici'
                                                        : 'Une journée à organiser'
                                                }
                                                text={
                                                    isClient
                                                        ? 'Les comptes rendus validés par votre conciergerie apparaîtront ici.'
                                                        : 'Ajoutez vos clients, vos lieux et votre équipe, puis planifiez votre première mission.'
                                                }
                                                action={
                                                    isManager
                                                        ? {
                                                              label: props
                                                                  .clients
                                                                  .length
                                                                  ? 'Planifier une mission'
                                                                  : 'Ajouter mon premier client',
                                                              onClick: () =>
                                                                  open(
                                                                      props
                                                                          .clients
                                                                          .length
                                                                          ? 'new-mission'
                                                                          : 'new-client',
                                                                  ),
                                                          }
                                                        : undefined
                                                }
                                            />
                                        )}
                                    </section>
                                    {isManager ? (
                                        <section className="workspace-card">
                                            <div className="workspace-card-heading">
                                                <h2>Activité récente</h2>
                                                <span className="text-xs text-slate-400">
                                                    Historique
                                                </span>
                                            </div>
                                            {props.activities.length ? (
                                                props.activities
                                                    .slice(0, 5)
                                                    .map((a) => (
                                                        <div
                                                            className="activity-item"
                                                            key={a.id}
                                                        >
                                                            <span>
                                                                <CheckCheck
                                                                    size={14}
                                                                />
                                                            </span>
                                                            <div>
                                                                <b>
                                                                    {
                                                                        a.description
                                                                    }
                                                                </b>
                                                                <p>
                                                                    {
                                                                        a.user
                                                                            .name
                                                                    }{' '}
                                                                    ·{' '}
                                                                    {dateLabel(
                                                                        a.created_at,
                                                                        true,
                                                                    )}
                                                                </p>
                                                            </div>
                                                        </div>
                                                    ))
                                            ) : (
                                                <Empty
                                                    title="Votre historique est prêt"
                                                    text="Les affectations, les décisions et les publications seront enregistrées ici."
                                                />
                                            )}
                                        </section>
                                    ) : null}
                                </div>
                                <div className="dashboard-secondary">
                                    <section className="workspace-card">
                                        <Calendar
                                            missions={props.missions}
                                            selected={selectedDay}
                                            onSelect={setSelectedDay}
                                        />
                                    </section>
                                    <section className="workspace-card">
                                        <div className="workspace-card-heading">
                                            <h2>
                                                {isClient
                                                    ? 'Votre conciergerie'
                                                    : 'Dernier compte rendu'}
                                            </h2>
                                            {!isClient ? (
                                                <Link
                                                    href={dashboard({
                                                        query: {
                                                            view: 'reports',
                                                        },
                                                    })}
                                                >
                                                    Voir tout →
                                                </Link>
                                            ) : null}
                                        </div>
                                        {isClient ? (
                                            <div className="directory-card">
                                                <h2>{props.concierge.name}</h2>
                                                <p>
                                                    Une demande, une question ou
                                                    un problème à signaler ?
                                                    Votre conciergerie vous
                                                    répond dans la messagerie.
                                                </p>
                                                <Link
                                                    className="brand-button small mt-4"
                                                    href={dashboard({
                                                        query: {
                                                            view: 'messages',
                                                        },
                                                    })}
                                                >
                                                    Écrire un message
                                                </Link>
                                            </div>
                                        ) : props.missions.find(
                                              (m) => m.reports.length,
                                          ) ? (
                                            <div className="directory-card">
                                                <h2>
                                                    {
                                                        props.missions.find(
                                                            (m) =>
                                                                m.reports
                                                                    .length,
                                                        )?.title
                                                    }
                                                </h2>
                                                <p>
                                                    {
                                                        props.missions.find(
                                                            (m) =>
                                                                m.reports
                                                                    .length,
                                                        )?.property.name
                                                    }
                                                </p>
                                                <button
                                                    className="brand-button small mt-4"
                                                    onClick={() =>
                                                        showMission(
                                                            props.missions.find(
                                                                (m) =>
                                                                    m.reports
                                                                        .length,
                                                            )!,
                                                        )
                                                    }
                                                >
                                                    Consulter le compte rendu
                                                </button>
                                            </div>
                                        ) : (
                                            <Empty
                                                title="Des interventions documentées"
                                                text="Vos observations et vos photos seront réunies dans chaque compte rendu."
                                            />
                                        )}
                                    </section>
                                </div>
                            </div>
                        </>
                    ) : null}
                    {view === 'missions' || view === 'reports' ? (
                        <>
                            <div className="workspace-search">
                                <Search size={16} />
                                <input
                                    aria-label="Rechercher une mission"
                                    placeholder="Rechercher une mission, un lieu…"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                />
                            </div>
                            <section className="workspace-card">
                                {(view === 'reports'
                                    ? missionList.filter(
                                          (m) => m.reports.length,
                                      )
                                    : missionList
                                ).length ? (
                                    (view === 'reports'
                                        ? missionList.filter(
                                              (m) => m.reports.length,
                                          )
                                        : missionList
                                    ).map((m) => (
                                        <MissionRow
                                            key={m.id}
                                            mission={m}
                                            onClick={() => showMission(m)}
                                        />
                                    ))
                                ) : (
                                    <Empty
                                        title={
                                            view === 'reports'
                                                ? 'Aucun compte rendu pour le moment'
                                                : 'Aucune mission trouvée'
                                        }
                                        text="Les missions enregistrées et leurs preuves apparaissent dans cet espace."
                                        action={
                                            isManager
                                                ? {
                                                      label: 'Planifier une mission',
                                                      onClick: () =>
                                                          open('new-mission'),
                                                  }
                                                : undefined
                                        }
                                    />
                                )}
                            </section>
                        </>
                    ) : null}
                    {view === 'planning' && isManager ? (
                        <div className="dashboard-grid">
                            <div className="dashboard-primary">
                                <section className="workspace-card">
                                    <div className="workspace-card-heading">
                                        <h2>
                                            Missions du {dateLabel(selectedDay)}
                                        </h2>
                                        <button
                                            onClick={() => open('new-mission')}
                                        >
                                            Planifier →
                                        </button>
                                    </div>
                                    {dayMissions.length ? (
                                        dayMissions.map((m) => (
                                            <MissionRow
                                                key={m.id}
                                                mission={m}
                                                onClick={() => showMission(m)}
                                            />
                                        ))
                                    ) : (
                                        <Empty
                                            title="Aucune mission ce jour"
                                            text="Sélectionnez une date dans le calendrier ou planifiez une intervention."
                                        />
                                    )}
                                </section>
                                <section className="workspace-card">
                                    <div className="workspace-card-heading">
                                        <h2>Séjours et indisponibilités</h2>
                                        <button onClick={() => open('import')}>
                                            Importer iCal →
                                        </button>
                                    </div>
                                    {visibleStays.length ? (
                                        visibleStays.map((s) => (
                                            <div
                                                className="activity-item"
                                                key={s.id}
                                            >
                                                <span>
                                                    <CalendarDays size={17} />
                                                </span>
                                                <div>
                                                    <b>{s.title}</b>
                                                    <DirectoryActions
                                                        resource="stays"
                                                        record={s}
                                                    />
                                                    <p>
                                                        {s.property.name} ·{' '}
                                                        {dateLabel(s.starts_on)}{' '}
                                                        → {dateLabel(s.ends_on)}{' '}
                                                        · {s.source}
                                                    </p>
                                                    <span
                                                        className={
                                                            'status-chip ' +
                                                            (s.kind ===
                                                            'unclassified'
                                                                ? 'amber'
                                                                : 'blue')
                                                        }
                                                    >
                                                        {s.kind ===
                                                        'unclassified'
                                                            ? 'À qualifier'
                                                            : s.kind ===
                                                                'blocked'
                                                              ? 'Indisponibilité'
                                                              : 'Séjour'}
                                                    </span>
                                                </div>
                                            </div>
                                        ))
                                    ) : (
                                        <Empty
                                            title="Votre calendrier de séjours"
                                            text="Saisissez une période ou importez un fichier .ics exporté d’Airbnb, Booking ou d’un autre calendrier."
                                        />
                                    )}
                                </section>
                            </div>
                            <section className="workspace-card">
                                <Calendar
                                    missions={props.missions}
                                    selected={selectedDay}
                                    onSelect={setSelectedDay}
                                />
                            </section>
                        </div>
                    ) : null}
                    {view === 'clients' && isManager ? (
                        <div className="directory-grid">
                            {visibleClients.length ? (
                                visibleClients.map((c) => (
                                    <article
                                        className="workspace-card directory-card"
                                        key={c.id}
                                    >
                                        <span className="directory-icon">
                                            <Users size={22} />
                                        </span>
                                        <h2>{c.name}</h2>
                                        <DirectoryActions
                                            resource="clients"
                                            record={c}
                                        />
                                        <p>
                                            {c.email}
                                            <br />
                                            {c.phone ??
                                                'Aucun téléphone renseigné'}
                                        </p>
                                        <p className="mt-3">
                                            {
                                                props.properties.filter(
                                                    (p) => p.client_id === c.id,
                                                ).length
                                            }{' '}
                                            lieux suivis ·{' '}
                                            {c.kind === 'company'
                                                ? 'Professionnel'
                                                : 'Particulier'}
                                        </p>
                                        <button
                                            className="mt-4 text-xs font-semibold text-teal"
                                            onClick={() => {
                                                setContact(c.id);
                                                router.visit(
                                                    dashboard({
                                                        query: {
                                                            view: 'messages',
                                                        },
                                                    }),
                                                );
                                            }}
                                        >
                                            Ouvrir la conversation →
                                        </button>
                                    </article>
                                ))
                            ) : (
                                <div className="workspace-card col-span-full">
                                    <Empty
                                        title="Vos clients, au même endroit"
                                        text="Ajoutez les particuliers et les entreprises accompagnés par votre conciergerie."
                                        action={{
                                            label: 'Ajouter un client',
                                            onClick: () => open('new-client'),
                                        }}
                                    />
                                </div>
                            )}
                        </div>
                    ) : null}
                    {view === 'properties' ? (
                        <div className="directory-grid">
                            {visibleProperties.length ? (
                                visibleProperties.map((p) => (
                                    <article
                                        className="workspace-card directory-card"
                                        key={p.id}
                                    >
                                        <span className="directory-icon">
                                            {p.kind === 'business' ? (
                                                <Building2 size={22} />
                                            ) : (
                                                <House size={22} />
                                            )}
                                        </span>
                                        <h2>{p.name}</h2>
                                        {isManager ? (
                                            <DirectoryActions
                                                resource="properties"
                                                record={p}
                                            />
                                        ) : null}
                                        <p>{p.address}</p>
                                        <p className="mt-3">{p.client?.name}</p>
                                        <span className="status-chip green">
                                            {
                                                propertyKinds.find(
                                                    (k) => k.value === p.kind,
                                                )?.label
                                            }
                                        </span>
                                        {!isClient ? (
                                            <p className="mt-3 whitespace-pre-wrap">
                                                {p.instructions}
                                            </p>
                                        ) : null}
                                    </article>
                                ))
                            ) : (
                                <div className="workspace-card col-span-full">
                                    <Empty
                                        title="Ajoutez votre premier lieu"
                                        text="Logement, résidence, entreprise : chaque lieu est rattaché à un client."
                                        action={
                                            isManager
                                                ? {
                                                      label: 'Ajouter un lieu',
                                                      onClick: () =>
                                                          open('new-property'),
                                                  }
                                                : undefined
                                        }
                                    />
                                </div>
                            )}
                        </div>
                    ) : null}
                    {view === 'team' && isManager ? (
                        <div className="directory-grid">
                            {props.members.map((m) => (
                                <article
                                    className="workspace-card directory-card"
                                    key={m.id}
                                >
                                    <div className="user-avatar mb-4">
                                        {initials(m.user.name)}
                                    </div>
                                    <h2>{m.user.name}</h2>
                                    <p>
                                        {m.user.email}
                                        <br />
                                        {m.team ?? 'Équipe principale'}
                                        {m.company ? ' · ' + m.company : ''}
                                    </p>
                                    <span className="status-chip green">
                                        {roleNames[m.role]}
                                    </span>
                                    <p className="mt-3">
                                        {
                                            props.missions.filter(
                                                (mission) =>
                                                    mission.assigned_user_id ===
                                                        m.user_id &&
                                                    ![
                                                        'validated',
                                                        'cancelled',
                                                    ].includes(mission.status),
                                            ).length
                                        }{' '}
                                        missions actives
                                    </p>
                                </article>
                            ))}
                        </div>
                    ) : null}
                    {view === 'templates' && isManager ? (
                        <div className="directory-grid">
                            {visibleTemplates.map((t) => (
                                <article
                                    className="workspace-card directory-card"
                                    key={t.id}
                                >
                                    <span className="directory-icon">
                                        <ClipboardList size={22} />
                                    </span>
                                    <h2>{t.name}</h2>
                                    <DirectoryActions
                                        resource="templates"
                                        record={t}
                                    />
                                    {t.checklist.map((item, i) => (
                                        <p
                                            className="flex items-start gap-2"
                                            key={i}
                                        >
                                            <Check
                                                className="mt-1 shrink-0 text-teal"
                                                size={12}
                                            />
                                            {item}
                                        </p>
                                    ))}
                                    <p className="form-hint mt-4">
                                        La checklist est copiée dans chaque
                                        nouvelle mission.
                                    </p>
                                </article>
                            ))}
                        </div>
                    ) : null}
                    {view === 'notifications' ? (
                        <NotificationsPanel inbox={props.notificationInbox} />
                    ) : null}
                    {view === 'recurrences' && isManager ? (
                        <RecurrencesPanel props={props} />
                    ) : null}
                    {view === 'availability' && !isClient ? (
                        <AvailabilityPanel props={props} user={auth.user} />
                    ) : null}
                    {view === 'reports' && (isManager || isClient) ? (
                        <MonthlyReportButton props={props} />
                    ) : null}
                    {view === 'incidents' ? (
                        <div className="directory-grid">
                            {props.incidents.length ? (
                                props.incidents.map((i) => (
                                    <article
                                        className="workspace-card directory-card"
                                        key={i.id}
                                    >
                                        <span className="directory-icon">
                                            <TriangleAlert size={22} />
                                        </span>
                                        <h2
                                            id={`incident-${i.id}`}
                                            className="scroll-mt-6"
                                        >
                                            {i.mission.property.name}
                                        </h2>
                                        <Status status={i.status} />
                                        <p className="mt-3 whitespace-pre-wrap">
                                            {i.description}
                                        </p>
                                        <p className="mt-3">
                                            {dateLabel(i.created_at, true)} ·{' '}
                                            {i.author.name}
                                        </p>
                                        {isManager ? (
                                            <IncidentFollowUp
                                                incident={i}
                                                props={props}
                                            />
                                        ) : null}
                                        {isManager ? (
                                            <button
                                                className="brand-button small mt-4"
                                                onClick={() => {
                                                    setSelectedIncident(i);
                                                    setSelectedMissionId(
                                                        i.mission_id,
                                                    );
                                                    open('share-incident');
                                                }}
                                            >
                                                Transmettre au client
                                            </button>
                                        ) : null}
                                        {isClient &&
                                        i.shared_photo_ids?.length ? (
                                            <div className="report-gallery">
                                                {i.shared_photo_ids.map(
                                                    (id) => (
                                                        <a
                                                            href={photoRoutes.show.url(
                                                                id,
                                                            )}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            key={id}
                                                        >
                                                            <img
                                                                src={photoRoutes.show.url(
                                                                    id,
                                                                )}
                                                                alt="Preuve sélectionnée par votre conciergerie"
                                                                loading="lazy"
                                                            />
                                                        </a>
                                                    ),
                                                )}
                                            </div>
                                        ) : null}
                                        {i.shared_at ? (
                                            <p className="mt-3 text-teal">
                                                Transmis au client le{' '}
                                                {dateLabel(i.shared_at, true)}
                                            </p>
                                        ) : null}
                                    </article>
                                ))
                            ) : (
                                <div className="workspace-card col-span-full">
                                    <Empty
                                        title="Aucun incident signalé"
                                        text={
                                            isClient
                                                ? 'Votre conciergerie partage ici les informations utiles et les preuves sélectionnées.'
                                                : 'Les signalements reçus du terrain sont visibles immédiatement, avant la validation des comptes rendus.'
                                        }
                                    />
                                </div>
                            )}
                        </div>
                    ) : null}
                    {view === 'requests' ? (
                        <div className="directory-grid">
                            {props.requests.length ? (
                                props.requests.map((r) => (
                                    <article
                                        className="workspace-card directory-card"
                                        key={r.id}
                                    >
                                        <h2>{r.title}</h2>
                                        <Status status={r.status} />
                                        <p className="mt-3">
                                            {r.property.name}
                                        </p>
                                        <p className="mt-3 whitespace-pre-wrap">
                                            {r.description}
                                        </p>
                                        {r.decision ? (
                                            <div className="detail-block">
                                                <h3>
                                                    Décision de la conciergerie
                                                </h3>
                                                {r.decision}
                                            </div>
                                        ) : null}
                                        {isManager && r.status === 'pending' ? (
                                            <button
                                                className="brand-button small mt-4"
                                                onClick={() => {
                                                    setSelectedRequest(r);
                                                    open('decide-request');
                                                }}
                                            >
                                                Traiter la demande
                                            </button>
                                        ) : null}
                                    </article>
                                ))
                            ) : (
                                <div className="workspace-card col-span-full">
                                    <Empty
                                        title="Aucune demande en attente"
                                        text="Chaque demande client doit être validée par le responsable avant de devenir une mission."
                                        action={
                                            isClient
                                                ? {
                                                      label: 'Demander une intervention',
                                                      onClick: () =>
                                                          open('new-request'),
                                                  }
                                                : undefined
                                        }
                                    />
                                </div>
                            )}
                        </div>
                    ) : null}
                    {view === 'messages' ? (
                        <section className="workspace-card message-layout">
                            <aside className="message-contacts">
                                {props.clients.map((c) => (
                                    <button
                                        key={c.id}
                                        className={
                                            contact === c.id ? 'active' : ''
                                        }
                                        onClick={() => setContact(c.id)}
                                    >
                                        {c.name}
                                    </button>
                                ))}
                            </aside>
                            <div className="message-thread">
                                {contact ? (
                                    <>
                                        <h2 className="text-sm font-semibold">
                                            {
                                                props.clients.find(
                                                    (c) => c.id === contact,
                                                )?.name
                                            }
                                        </h2>
                                        <div className="message-bubbles">
                                            {props.messages.filter(
                                                (m) => m.client_id === contact,
                                            ).length ? (
                                                props.messages
                                                    .filter(
                                                        (m) =>
                                                            m.client_id ===
                                                            contact,
                                                    )
                                                    .map((m) => (
                                                        <div
                                                            className={
                                                                'message-bubble ' +
                                                                (m.author_id ===
                                                                auth.user.id
                                                                    ? 'mine'
                                                                    : '')
                                                            }
                                                            key={m.id}
                                                        >
                                                            <b>
                                                                {m.author.name}
                                                            </b>
                                                            <p>{m.body}</p>
                                                            <small>
                                                                {dateLabel(
                                                                    m.created_at,
                                                                    true,
                                                                )}
                                                            </small>
                                                        </div>
                                                    ))
                                            ) : (
                                                <Empty
                                                    title="Le début d’un échange"
                                                    text="Posez une question ou donnez une information à votre interlocuteur."
                                                />
                                            )}
                                        </div>
                                        <BusinessForm
                                            action={messageRoutes.store.url()}
                                            submit="Envoyer"
                                            key={contact}
                                            onSuccess={() => {}}
                                        >
                                            <input
                                                type="hidden"
                                                name="client_id"
                                                value={contact}
                                            />
                                            <TextArea
                                                name="body"
                                                label="Votre message"
                                                required
                                                placeholder="Écrivez votre message…"
                                            />
                                        </BusinessForm>
                                    </>
                                ) : (
                                    <Empty
                                        title="Sélectionnez un client"
                                        text="Ajoutez un client pour ouvrir votre première conversation."
                                    />
                                )}
                            </div>
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
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>
                            {modalNames[modal ?? ''] ?? 'Votre espace'}
                        </DialogTitle>
                        <DialogDescription>
                            {modal === 'mission-detail'
                                ? selectedMission?.property.name
                                : 'Les informations sont enregistrées dans votre espace privé.'}
                        </DialogDescription>
                    </DialogHeader>
                    {modal === 'new-client' ? (
                        <BusinessForm
                            action={clientRoutes.store.url()}
                            onSuccess={close}
                        >
                            <Field
                                label="Nom / raison sociale"
                                name="name"
                                required
                            />
                            <div className="form-grid">
                                <Field
                                    label="Adresse e-mail"
                                    name="email"
                                    type="email"
                                    required
                                />
                                <Field
                                    label="Téléphone"
                                    name="phone"
                                    type="tel"
                                />
                            </div>
                            <SelectField
                                label="Type de client"
                                name="kind"
                                required
                                defaultValue="individual"
                                empty=""
                                items={[
                                    {
                                        value: 'individual',
                                        label: 'Particulier',
                                    },
                                    {
                                        value: 'company',
                                        label: 'Professionnel',
                                    },
                                ]}
                            />
                        </BusinessForm>
                    ) : null}
                    {modal === 'new-property' ? (
                        clientOptions.length ? (
                            <BusinessForm
                                action={propertyRoutes.store.url()}
                                onSuccess={close}
                            >
                                <SelectField
                                    label="Client"
                                    name="client_id"
                                    items={clientOptions}
                                    required
                                />
                                <Field
                                    label="Nom du lieu"
                                    name="name"
                                    required
                                />
                                <Field
                                    label="Adresse complète"
                                    name="address"
                                    required
                                />
                                <SelectField
                                    label="Type de lieu"
                                    name="kind"
                                    items={propertyKinds}
                                    required
                                    defaultValue="rental"
                                />
                                <TextArea
                                    label="Consignes générales"
                                    name="instructions"
                                />
                                <Field
                                    label="Code d’accès (chiffré, révélé après acceptation)"
                                    name="access_code"
                                />
                                <label className="flex items-center gap-2 text-xs">
                                    <input
                                        name="offline_access"
                                        type="checkbox"
                                        value="1"
                                    />
                                    Autoriser le code dans les missions
                                    préparées hors connexion
                                </label>
                            </BusinessForm>
                        ) : (
                            <Empty
                                title="Ajoutez d’abord un client"
                                text="Chaque lieu appartient à un client de votre conciergerie."
                                action={{
                                    label: 'Ajouter un client',
                                    onClick: () => open('new-client'),
                                }}
                            />
                        )
                    ) : null}
                    {modal === 'new-member' ? (
                        <BusinessForm
                            action={memberRoutes.store.url()}
                            onSuccess={close}
                        >
                            <Field
                                label="Nom de la personne"
                                name="name"
                                required
                            />
                            <Field
                                label="E-mail de connexion"
                                name="email"
                                type="email"
                                required
                            />
                            <SelectField
                                label="Rôle"
                                name="role"
                                required
                                empty=""
                                defaultValue={memberRole}
                                onChange={setMemberRole}
                                items={[
                                    { value: 'worker', label: 'Intervenant' },
                                    { value: 'manager', label: 'Responsable' },
                                    { value: 'client', label: 'Client' },
                                    {
                                        value: 'coordinator',
                                        label: 'Responsable d’une société prestataire',
                                    },
                                ]}
                            />
                            {memberRole === 'client' ? (
                                <SelectField
                                    label="Client autorisé"
                                    name="client_id"
                                    items={clientOptions}
                                    required
                                />
                            ) : (
                                <div className="form-grid">
                                    <Field
                                        label="Agence / équipe"
                                        name="team"
                                    />
                                    <Field
                                        label="Société prestataire (facultatif)"
                                        name="company"
                                        required={memberRole === 'coordinator'}
                                    />
                                </div>
                            )}
                            <div className="form-grid">
                                <Field
                                    label="Mot de passe initial (12 caractères minimum)"
                                    name="password"
                                    type="password"
                                    required
                                    min={12}
                                />
                                <Field
                                    label="Confirmer le mot de passe"
                                    name="password_confirmation"
                                    type="password"
                                    required
                                />
                            </div>
                            <p className="form-hint">
                                Chaque personne dispose de son propre compte.
                                Communiquez les identifiants directement à leur
                                titulaire.
                            </p>
                        </BusinessForm>
                    ) : null}
                    {modal === 'new-template' ? (
                        <BusinessForm
                            action={templateRoutes.store.url()}
                            onSuccess={close}
                            transform={(data) => ({
                                ...data,
                                checklist: String(
                                    'checklist_text' in data
                                        ? data.checklist_text
                                        : '',
                                )
                                    .split('\n')
                                    .map((line) => line.trim())
                                    .filter(Boolean),
                            })}
                        >
                            <Field
                                label="Nom de la prestation"
                                name="name"
                                required
                            />
                            <TextArea
                                label="Points de la checklist (un par ligne)"
                                name="checklist_text"
                                required
                                placeholder={
                                    'Vérifier l’état général\nContrôler les équipements\nPhotographier les points de contrôle'
                                }
                            />
                        </BusinessForm>
                    ) : null}
                    {modal === 'new-mission' ? (
                        propertyOptions.length ? (
                            <BusinessForm
                                action={missionRoutes.store.url()}
                                onSuccess={close}
                            >
                                <Field
                                    label="Intitulé de la mission"
                                    name="title"
                                    required
                                />
                                <div className="form-grid">
                                    <SelectField
                                        label="Lieu d’intervention"
                                        name="property_id"
                                        items={propertyOptions}
                                        required
                                    />
                                    <SelectField
                                        label="Prestation / checklist"
                                        name="template_id"
                                        items={templateOptions}
                                        required
                                    />
                                </div>
                                <div className="form-grid">
                                    <Field
                                        label="Date et heure"
                                        name="scheduled_at"
                                        type="datetime-local"
                                        required
                                        defaultValue={selectedDay + 'T09:00'}
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
                                <SelectField
                                    name="assignment_mode"
                                    label="Mode d’affectation"
                                    empty=""
                                    defaultValue={assignmentMode}
                                    onChange={setAssignmentMode}
                                    items={[
                                        {
                                            value: 'individual',
                                            label: 'Choisir une personne',
                                        },
                                        {
                                            value: 'company',
                                            label: 'Confier à une entreprise prestataire',
                                        },
                                    ]}
                                />
                                {assignmentMode === 'individual' ? (
                                    <SelectField
                                        label="Intervenant"
                                        name="assigned_user_id"
                                        items={assigneeOptions}
                                        empty="À affecter"
                                    />
                                ) : (
                                    <SelectField
                                        name="assigned_company"
                                        label="Entreprise prestataire"
                                        required
                                        items={props.members
                                            .filter(
                                                (m) =>
                                                    m.role === 'coordinator' &&
                                                    m.company,
                                            )
                                            .map((m) => ({
                                                value: m.company!,
                                                label: m.company!,
                                            }))}
                                    />
                                )}
                                <SelectField
                                    label="Nature du contrôle"
                                    name="kind"
                                    items={[
                                        {
                                            value: 'service',
                                            label: 'Prestation (avant / après)',
                                        },
                                        {
                                            value: 'initial',
                                            label: 'État initial du bien',
                                        },
                                        {
                                            value: 'arrival',
                                            label: 'Constat d’arrivée',
                                        },
                                        {
                                            value: 'departure',
                                            label: 'Constat de départ',
                                        },
                                    ]}
                                    defaultValue="service"
                                    required
                                    empty=""
                                />
                                <TextArea
                                    label="Consignes de la mission"
                                    name="instructions"
                                />
                            </BusinessForm>
                        ) : (
                            <Empty
                                title="Préparez votre premier lieu"
                                text="Un client et un lieu sont nécessaires pour planifier une mission."
                                action={{
                                    label: 'Ajouter un lieu',
                                    onClick: () => open('new-property'),
                                }}
                            />
                        )
                    ) : null}
                    {modal === 'new-stay' ? (
                        <BusinessForm
                            action={stayRoutes.store.url()}
                            onSuccess={close}
                        >
                            <Field
                                label="Intitulé de la période"
                                name="title"
                                required
                            />
                            <SelectField
                                label="Lieu"
                                name="property_id"
                                items={propertyOptions}
                                required
                            />
                            <div className="form-grid">
                                <Field
                                    label="Début"
                                    name="starts_on"
                                    type="date"
                                    required
                                />
                                <Field
                                    label="Fin (jour de départ, exclu)"
                                    name="ends_on"
                                    type="date"
                                    required
                                />
                            </div>
                            <SelectField
                                label="Nature"
                                name="kind"
                                defaultValue="stay"
                                required
                                empty=""
                                items={[
                                    { value: 'stay', label: 'Séjour' },
                                    {
                                        value: 'blocked',
                                        label: 'Indisponibilité',
                                    },
                                ]}
                            />
                        </BusinessForm>
                    ) : null}
                    {modal === 'import' ? (
                        <BusinessForm
                            action={stayRoutes.importMethod.url()}
                            onSuccess={close}
                            submit="Importer le fichier"
                        >
                            <SelectField
                                label="Lieu"
                                name="property_id"
                                items={propertyOptions}
                                required
                            />
                            <SelectField
                                label="Source"
                                name="source"
                                items={[
                                    { value: 'airbnb', label: 'Airbnb' },
                                    { value: 'booking', label: 'Booking' },
                                    {
                                        value: 'ical',
                                        label: 'Autre calendrier iCal',
                                    },
                                ]}
                                required
                            />
                            <label className="form-field">
                                Calendrier exporté (.ics)
                                <input
                                    name="calendar"
                                    type="file"
                                    accept=".ics,text/calendar"
                                    required
                                />
                            </label>
                            <p className="form-hint">
                                Les événements sont dédoublonnés par leur
                                identifiant et restent à qualifier. Cet import
                                de fichier ne crée pas automatiquement de
                                mission et n’actualise pas un flux à distance.
                            </p>
                        </BusinessForm>
                    ) : null}
                    {modal === 'mission-detail' && selectedMission ? (
                        <>
                            <Status status={selectedMission.status} />
                            <RescheduleMissionButton
                                mission={selectedMission}
                                props={props}
                            />
                            {props.membership.role === 'coordinator' &&
                            selectedMission.status === 'planned' ? (
                                <button
                                    className="brand-button small"
                                    onClick={() => open('designate')}
                                >
                                    Désigner un intervenant
                                </button>
                            ) : null}
                            <div className="mission-detail-meta">
                                <div>
                                    <b>Intervention</b>
                                    {dateLabel(
                                        selectedMission.scheduled_at,
                                        true,
                                    )}{' '}
                                    · {selectedMission.duration} min
                                </div>
                                <div>
                                    <b>Intervenant</b>
                                    {selectedMission.assignee?.name ??
                                        'À affecter'}
                                </div>
                                <div>
                                    <b>Adresse</b>
                                    {selectedMission.property.address}
                                </div>
                                <div>
                                    <b>Contrôle</b>
                                    {
                                        {
                                            service: 'Prestation avant / après',
                                            initial: 'État initial',
                                            arrival: 'Arrivée',
                                            departure: 'Départ',
                                        }[selectedMission.kind]
                                    }
                                </div>
                            </div>
                            {!isClient ? (
                                <>
                                    <div className="detail-block">
                                        <h3>Consignes</h3>
                                        {selectedMission.accessible_code ? (
                                            <p>
                                                <b>Code d’accès :</b>{' '}
                                                {
                                                    selectedMission.accessible_code
                                                }
                                            </p>
                                        ) : null}
                                        {selectedMission.instructions ??
                                            selectedMission.property
                                                .instructions ??
                                            'Aucune consigne complémentaire.'}
                                    </div>
                                    <div className="checklist-items">
                                        {selectedMission.checklist.map(
                                            (item, i) => (
                                                <div
                                                    className="flex items-center gap-2 text-xs"
                                                    key={i}
                                                >
                                                    <Check
                                                        size={14}
                                                        className="text-teal"
                                                    />
                                                    {item}
                                                </div>
                                            ),
                                        )}
                                    </div>
                                </>
                            ) : null}
                            {currentReport?.status === 'validated' &&
                            (isManager || isClient) ? (
                                <ReportPdfLink id={currentReport.id} />
                            ) : null}
                            {currentReport ? (
                                <>
                                    <div className="detail-block">
                                        <h3>
                                            Compte rendu ·{' '}
                                            {currentReport.author.name}
                                        </h3>
                                        {currentReport.notes}
                                        {currentReport.feedback ? (
                                            <p className="mt-3 text-red-600">
                                                Reprise :{' '}
                                                {currentReport.feedback}
                                            </p>
                                        ) : null}
                                        {currentReport.reviewed_at ? (
                                            <p className="mt-3 text-teal">
                                                Décision de{' '}
                                                {currentReport.reviewer?.name} ·{' '}
                                                {dateLabel(
                                                    currentReport.reviewed_at,
                                                    true,
                                                )}
                                            </p>
                                        ) : null}
                                    </div>
                                    <div className="report-gallery">
                                        {currentReport.photos.map((photo) => (
                                            <a
                                                key={photo.id}
                                                href={photoRoutes.show.url(
                                                    photo.id,
                                                )}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <img
                                                    src={photoRoutes.show.url(
                                                        photo.id,
                                                    )}
                                                    loading="lazy"
                                                    alt={
                                                        'Preuve ' +
                                                        {
                                                            before: 'avant',
                                                            after: 'après',
                                                            observation:
                                                                'du constat',
                                                        }[photo.stage]
                                                    }
                                                />
                                                <span>
                                                    {
                                                        {
                                                            before: 'Avant',
                                                            after: 'Après',
                                                            observation:
                                                                'Observation',
                                                        }[photo.stage]
                                                    }{' '}
                                                    ·{' '}
                                                    {photo.captured_at
                                                        ? 'Capture déclarée ' +
                                                          dateLabel(
                                                              photo.captured_at,
                                                              true,
                                                          )
                                                        : 'Date de capture inconnue'}
                                                </span>
                                            </a>
                                        ))}
                                    </div>
                                    {isManager &&
                                    currentReport.status === 'submitted' ? (
                                        <button
                                            className="brand-button small"
                                            onClick={() => open('review')}
                                        >
                                            Valider ou demander une reprise
                                        </button>
                                    ) : null}
                                </>
                            ) : null}
                            {!isClient &&
                            props.membership.role !== 'coordinator' ? (
                                <div className="mt-3 flex flex-wrap gap-3">
                                    {selectedMission.status === 'planned' &&
                                    selectedMission.assigned_user_id ===
                                        auth.user.id ? (
                                        <BusinessForm
                                            action={missionRoutes.transition.url(
                                                selectedMission.id,
                                            )}
                                            submit="Accepter la mission"
                                            onSuccess={close}
                                        >
                                            <input
                                                type="hidden"
                                                name="action"
                                                value="accept"
                                            />
                                            <input
                                                type="hidden"
                                                name="version"
                                                value={selectedMission.version}
                                            />
                                        </BusinessForm>
                                    ) : null}
                                    {[
                                        'accepted',
                                        'in_progress',
                                        'changes_requested',
                                    ].includes(selectedMission.status) ? (
                                        <>
                                            <button
                                                className="brand-button small"
                                                onClick={() => open('report')}
                                            >
                                                Rédiger le compte rendu
                                            </button>
                                            <button
                                                className="brand-button small secondary"
                                                onClick={() => open('incident')}
                                            >
                                                Signaler un incident
                                            </button>
                                            {selectedMission.assigned_user_id ===
                                            auth.user.id ? (
                                                <a
                                                    className="brand-button small secondary"
                                                    href="/terrain.html"
                                                >
                                                    Emporter sur le terrain
                                                </a>
                                            ) : null}
                                        </>
                                    ) : null}
                                    {isManager &&
                                    ![
                                        'validated',
                                        'submitted',
                                        'cancelled',
                                    ].includes(selectedMission.status) ? (
                                        <>
                                            <button
                                                className="brand-button small secondary"
                                                onClick={() => open('reassign')}
                                            >
                                                Réaffecter
                                            </button>
                                            <BusinessForm
                                                action={missionRoutes.transition.url(
                                                    selectedMission.id,
                                                )}
                                                submit="Annuler la mission"
                                                onSuccess={close}
                                            >
                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="cancel"
                                                />
                                                <input
                                                    type="hidden"
                                                    name="version"
                                                    value={
                                                        selectedMission.version
                                                    }
                                                />
                                            </BusinessForm>
                                        </>
                                    ) : null}
                                </div>
                            ) : null}
                        </>
                    ) : null}
                    {modal === 'report' && selectedMission ? (
                        <ReportForm
                            mission={selectedMission}
                            receiptId={receiptId}
                            close={close}
                        />
                    ) : null}
                    {modal === 'review' && currentReport ? (
                        <BusinessForm
                            action={reportRoutes.review.url(currentReport.id)}
                            onSuccess={close}
                            submit="Enregistrer la décision"
                        >
                            <SelectField
                                label="Décision"
                                name="decision"
                                required
                                defaultValue="validated"
                                empty=""
                                items={[
                                    {
                                        value: 'validated',
                                        label: 'Valider et rendre visible au client',
                                    },
                                    {
                                        value: 'changes_requested',
                                        label: 'Demander une reprise',
                                    },
                                ]}
                            />
                            <TextArea
                                label="Commentaire (obligatoire pour une reprise)"
                                name="feedback"
                            />
                            <p className="form-hint">
                                La validation conserve votre identité et sa
                                date, y compris lorsque vous êtes l’auteur de la
                                prestation.
                            </p>
                        </BusinessForm>
                    ) : null}
                    {modal === 'incident' && selectedMission ? (
                        <BusinessForm
                            action={missionRoutes.incident.url(
                                selectedMission.id,
                            )}
                            onSuccess={close}
                            submit="Signaler au responsable"
                        >
                            <input
                                name="operation_id"
                                type="hidden"
                                value={receiptId}
                            />
                            <input
                                name="version"
                                type="hidden"
                                value={selectedMission.version}
                            />
                            <TextArea
                                label="Décrivez le problème observé"
                                name="description"
                                required
                            />
                            <p className="form-hint">
                                Le signalement est reçu immédiatement. Il ne
                                devient visible au client qu’après une
                                transmission explicite du responsable.
                            </p>
                        </BusinessForm>
                    ) : null}
                    {modal === 'share-incident' && selectedIncident ? (
                        <BusinessForm
                            action={incidentRoutes.share.url(
                                selectedIncident.id,
                            )}
                            onSuccess={close}
                            submit="Transmettre au client"
                            transform={(data) => ({
                                ...data,
                                shared_photo_ids:
                                    'shared_photo_ids' in data &&
                                    Array.isArray(data.shared_photo_ids)
                                        ? data.shared_photo_ids.map(Number)
                                        : [],
                            })}
                        >
                            <div className="detail-block">
                                {selectedIncident.description}
                            </div>
                            <TextArea
                                label="Explication destinée au client"
                                name="client_message"
                                defaultValue={
                                    selectedIncident.client_message ?? ''
                                }
                                required
                            />
                            <input
                                type="hidden"
                                name="status"
                                value={selectedIncident.status}
                            />
                            <div className="report-gallery">
                                {reportPhotos.map((p) => (
                                    <label key={p.id}>
                                        <img
                                            src={photoRoutes.show.url(p.id)}
                                            alt="Preuve disponible"
                                        />
                                        <span>
                                            <input
                                                name="shared_photo_ids[]"
                                                value={p.id}
                                                type="checkbox"
                                            />
                                            Partager cette photo
                                        </span>
                                    </label>
                                ))}
                            </div>
                            <p className="form-hint">
                                Seuls cette explication et les fichiers
                                sélectionnés seront partagés. Les observations
                                internes restent privées.
                            </p>
                        </BusinessForm>
                    ) : null}
                    {modal === 'decide-request' && selectedRequest ? (
                        <BusinessForm
                            action={requestRoutes.decide.url(
                                selectedRequest.id,
                            )}
                            onSuccess={close}
                            submit="Enregistrer la décision"
                        >
                            <div className="detail-block">
                                {selectedRequest.description}
                            </div>
                            <SelectField
                                label="Décision"
                                name="decision"
                                defaultValue={requestDecision}
                                onChange={setRequestDecision}
                                required
                                empty=""
                                items={[
                                    {
                                        value: 'accepted',
                                        label: 'Valider et créer une mission',
                                    },
                                    {
                                        value: 'rejected',
                                        label: 'Refuser la demande',
                                    },
                                ]}
                            />
                            <TextArea
                                label="Réponse au client"
                                name="feedback"
                                required
                            />
                            {requestDecision === 'accepted' ? (
                                <>
                                    <Field
                                        label="Date de la mission"
                                        name="scheduled_at"
                                        type="datetime-local"
                                        required
                                    />
                                    <SelectField
                                        label="Prestation"
                                        name="template_id"
                                        items={templateOptions}
                                        required
                                    />
                                    <SelectField
                                        label="Intervenant"
                                        name="assigned_user_id"
                                        items={assigneeOptions}
                                        empty="À affecter"
                                    />
                                </>
                            ) : null}
                        </BusinessForm>
                    ) : null}
                    {modal === 'new-request' ? (
                        <BusinessForm
                            action={requestRoutes.store.url()}
                            onSuccess={close}
                            submit="Envoyer ma demande"
                        >
                            <SelectField
                                label="Lieu concerné"
                                name="property_id"
                                items={propertyOptions}
                                required
                            />
                            <Field
                                label="Objet de la demande"
                                name="title"
                                required
                            />
                            <TextArea
                                label="Votre besoin / problème"
                                name="description"
                                required
                            />
                        </BusinessForm>
                    ) : null}
                    {modal === 'reassign' && selectedMission ? (
                        <BusinessForm
                            action={missionRoutes.transition.url(
                                selectedMission.id,
                            )}
                            onSuccess={close}
                            submit="Réaffecter"
                        >
                            <input
                                name="action"
                                type="hidden"
                                value="reassign"
                            />
                            <input
                                name="version"
                                type="hidden"
                                value={selectedMission.version}
                            />
                            <SelectField
                                label="Nouvel intervenant"
                                name="assigned_user_id"
                                items={assigneeOptions}
                                required
                            />
                            <p className="form-hint">
                                La nouvelle affectation devra être acceptée. Les
                                preuves préparées avec l’ancienne version seront
                                conservées sur l’appareil et leur soumission
                                bloquée pour arbitrage.
                            </p>
                        </BusinessForm>
                    ) : null}
                    {modal === 'designate' && selectedMission ? (
                        <BusinessForm
                            action={missionRoutes.designate.url(
                                selectedMission.id,
                            )}
                            onSuccess={close}
                            submit="Désigner cet intervenant"
                        >
                            <input
                                name="version"
                                type="hidden"
                                value={selectedMission.version}
                            />
                            <SelectField
                                name="assigned_user_id"
                                label="Personne de votre entreprise"
                                items={assigneeOptions}
                                required
                            />
                            <p className="form-hint">
                                La personne désignée devra accepter la mission
                                avec son propre compte.
                            </p>
                        </BusinessForm>
                    ) : null}
                </DialogContent>
            </Dialog>
        </div>
    );
}
function DownloadIcon() {
    return <Camera size={16} />;
}

function ReportForm({
    mission,
    receiptId,
    close,
}: {
    mission: MissionRecord;
    receiptId: string;
    close: () => void;
}) {
    const [before, setBefore] = useState<File[]>([]);
    const [after, setAfter] = useState<File[]>([]);
    return (
        <BusinessForm
            action={missionRoutes.report.url(mission.id)}
            onSuccess={close}
            submit="Soumettre au responsable"
            transform={(data) => ({
                ...data,
                photos: [...before, ...after],
                stages: [
                    ...before.map(() => 'before'),
                    ...after.map(() =>
                        mission.kind === 'service' ? 'after' : 'observation',
                    ),
                ],
            })}
        >
            <input type="hidden" name="operation_id" value={receiptId} />
            <input type="hidden" name="version" value={mission.version} />
            <div className="checklist-items">
                {mission.checklist.map((item, i) => (
                    <label key={i}>
                        <input
                            type="checkbox"
                            name={'answers[' + i + ']'}
                            value="1"
                            required
                        />
                        {item}
                    </label>
                ))}
            </div>
            <TextArea
                label="Observations et compte rendu"
                name="notes"
                required
            />
            <label className="form-field">
                Photos avant
                <input
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    multiple
                    onChange={(event) =>
                        setBefore(Array.from(event.currentTarget.files ?? []))
                    }
                />
            </label>
            <label className="form-field">
                Photos après / constat
                <input
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    multiple
                    onChange={(event) =>
                        setAfter(Array.from(event.currentTarget.files ?? []))
                    }
                />
            </label>
            <p className="form-hint">
                {before.length + after.length} fichiers sélectionnés. 20 photos
                maximum, JPEG / PNG / WebP, 10 Mo chacune. Les originaux sont
                conservés. Pour un brouillon hors connexion, utilisez l’espace
                terrain.
            </p>
        </BusinessForm>
    );
}

function ShieldAdminIcon() {
    return <Settings size={17} />;
}
