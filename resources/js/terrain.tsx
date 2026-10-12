import '../../resources/css/app.css';
import { createRoot } from 'react-dom/client';
import { useEffect, useRef, useState } from 'react';
import {
    ArrowLeft,
    Camera,
    Check,
    Download,
    LockKeyhole,
    RefreshCw,
    Save,
    TriangleAlert,
    Wifi,
    WifiOff,
} from 'lucide-react';
import { Brand } from '@/components/concierge/brand';
import { operationId } from '@/components/concierge/business-form';
import { data as terrainData } from '@/routes/terrain';
import { dashboard, login } from '@/routes';
import {
    dataUrlBlob,
    decrypt,
    deriveKey,
    encrypt,
    fileAsDataUrl,
    readVault,
    writeVault,
} from '@/lib/terrain-store';
import type { EncryptedRecord, VaultMeta } from '@/lib/terrain-store';
type TerrainMission = {
    id: number;
    title: string;
    kind: string;
    scheduled_at: string;
    version: number;
    status: string;
    checklist: string[];
    instructions: string | null;
    property: {
        name: string;
        address: string;
        instructions: string | null;
        access_code: string | null;
    };
    feedback: string | null;
    report_url: string;
    incident_url: string;
};
type Bundle = {
    app_name?: string;
    identity: string;
    user: { id: number; name: string };
    concierge: string;
    missions: TerrainMission[];
    csrf?: string;
};
type LocalPhoto = {
    id: string;
    name: string;
    data: string;
    stage: string;
    captured_at: null;
};
type Draft = {
    mission: TerrainMission;
    notes: string;
    answers: boolean[];
    photos: LocalPhoto[];
    incident: string;
    reportOperation: string;
    incidentOperation: string;
    incidentReceived: boolean;
    state: 'draft' | 'pending' | 'received' | 'conflict' | 'error';
    error?: string;
};
type Drafts = Record<number, Draft>;
function Terrain() {
    const secure =
        window.isSecureContext &&
        'serviceWorker' in navigator &&
        Boolean(crypto.subtle);
    const [online, setOnline] = useState(navigator.onLine);
    const [bundle, setBundle] = useState<Bundle | null>(null);
    const [meta, setMeta] = useState<VaultMeta | null>(null);
    const [pin, setPin] = useState('');
    const [note, setNote] = useState('');
    const [busy, setBusy] = useState(false);
    const [ready, setReady] = useState(false);
    const [drafts, setDrafts] = useState<Drafts>({});
    const [selected, setSelected] = useState<number | null>(null);
    const [dirty, setDirty] = useState(false);
    const key = useRef<CryptoKey | null>(null);
    const draftRef = useRef<Drafts>({});
    const sending = useRef(false);
    const current = drafts[selected ?? 0];
    useEffect(() => {
        readVault<VaultMeta>('current-meta')
            .then((value) => setMeta(value ?? null))
            .catch(() =>
                setNote(
                    'Le stockage local n’est pas disponible sur cet appareil.',
                ),
            );
        const on = () => setOnline(true);
        const off = () => setOnline(false);
        window.addEventListener('online', on);
        window.addEventListener('offline', off);
        return () => {
            window.removeEventListener('online', on);
            window.removeEventListener('offline', off);
        };
    }, []);
    useEffect(() => {
        const warn = (event: BeforeUnloadEvent) => {
            if (dirty) {
                event.preventDefault();
                event.returnValue = '';
            }
        };
        window.addEventListener('beforeunload', warn);
        return () => window.removeEventListener('beforeunload', warn);
    }, [dirty]);
    async function fetchBundle(): Promise<Bundle> {
        const response = await fetch(terrainData.url(), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            cache: 'no-store',
        });
        if (!response.ok)
            throw new Error(
                response.status === 401 || response.status === 419
                    ? 'Reconnectez-vous à votre compte. Les preuves locales sont conservées.'
                    : 'Impossible de joindre votre espace ou accès non autorisé.',
            );
        return response.json() as Promise<Bundle>;
    }
    async function save(next: Drafts): Promise<void> {
        if (!key.current || !bundle)
            throw new Error('Déverrouillez votre espace terrain.');
        await writeVault([
            [bundle.identity + ':drafts', await encrypt(key.current, next)],
        ]);
        draftRef.current = next;
        setDrafts(next);
        setDirty(false);
    }
    async function unlock() {
        if (!secure) {
            setNote(
                'Le hors connexion exige HTTPS (certificat reconnu) ou localhost. Ouvrez cette page en HTTPS avec Laragon.',
            );
            return;
        }
        setBusy(true);
        setNote('');
        try {
            if (!meta)
                throw new Error(
                    'Préparez votre journée pendant que vous êtes connecté.',
                );
            const derived = await deriveKey(pin, meta.salt);
            const stored = await readVault<EncryptedRecord>(
                meta.identity + ':bundle',
            );
            if (!stored) throw new Error('Préparation introuvable.');
            const loaded = await decrypt<Bundle>(derived, stored);
            if (navigator.onLine) {
                const server = await fetchBundle().catch(() => null);
                if (server && server.identity !== loaded.identity)
                    throw new Error(
                        'Cet appareil contient les preuves d’un autre compte. Connectez-vous au compte qui les a préparées.',
                    );
            }
            const saved = await readVault<EncryptedRecord>(
                meta.identity + ':drafts',
            );
            const work = saved ? await decrypt<Drafts>(derived, saved) : {};
            key.current = derived;
            setBundle(loaded);
            draftRef.current = work;
            setDrafts(work);
            setPin('');
            setReady(true);
            setSelected(
                Number(Object.keys(work)[0]) || loaded.missions[0]?.id || null,
            );
            setNote(
                'Travail local retrouvé. Les envois reçus seront confirmés par le serveur.',
            );
        } catch (error) {
            setNote(
                error instanceof Error && error.name !== 'OperationError'
                    ? error.message
                    : 'Code de déverrouillage incorrect.',
            );
        } finally {
            setBusy(false);
        }
    }
    async function prepare() {
        setBusy(true);
        setNote('');
        try {
            if (!secure)
                throw new Error(
                    'Le hors connexion exige HTTPS avec un certificat reconnu, ou localhost.',
                );
            const fresh = await fetchBundle();
            if (pin.length < 8 && !key.current)
                throw new Error(
                    'Choisissez un code local d’au moins 8 caractères. Gardez-le pour retrouver votre travail hors réseau.',
                );
            let localKey = key.current;
            let localMeta = meta;
            if (!localKey) {
                const previous = await readVault<VaultMeta>(
                    fresh.identity + ':meta',
                );
                if (previous) {
                    throw new Error(
                        'Une journée existe déjà pour ce compte. Déverrouillez-la avant de la mettre à jour.',
                    );
                }
                const salt = crypto.getRandomValues(new Uint8Array(16));
                localKey = await deriveKey(pin, salt);
                localMeta = {
                    identity: fresh.identity,
                    salt,
                    preparedAt: new Date().toISOString(),
                };
            }
            if (
                !localMeta ||
                localMeta.identity !== fresh.identity ||
                (bundle && bundle.identity !== fresh.identity)
            )
                throw new Error(
                    'Reconnectez-vous au compte de votre travail local.',
                );
            await navigator.serviceWorker.register('/terrain-sw.js', {
                scope: '/',
            });
            await Promise.race([
                navigator.serviceWorker.ready,
                new Promise((_, reject) =>
                    setTimeout(
                        () =>
                            reject(
                                new Error(
                                    'La préparation des ressources a échoué. Vérifiez la connexion puis réessayez.',
                                ),
                            ),
                        15000,
                    ),
                ),
            ]);
            const { csrf: ignored, ...safeBundle } = fresh;
            void ignored;
            const work = { ...draftRef.current };
            for (const mission of fresh.missions) {
                if (
                    !work[mission.id] ||
                    work[mission.id].state === 'received'
                ) {
                    work[mission.id] = {
                        mission,
                        notes: '',
                        answers: mission.checklist.map(() => false),
                        photos: [],
                        incident: '',
                        reportOperation: operationId(),
                        incidentOperation: operationId(),
                        incidentReceived: false,
                        state: 'draft',
                    };
                } else if (
                    work[mission.id].mission.version !== mission.version
                ) {
                    work[mission.id] = {
                        ...work[mission.id],
                        state: 'conflict',
                        error: 'La version de la mission a changé. Preuves conservées : contactez le responsable.',
                    };
                }
            }
            for (const id of Object.keys(work)) {
                if (
                    !fresh.missions.some((m) => m.id === Number(id)) &&
                    work[Number(id)].state !== 'received'
                ) {
                    work[Number(id)] = {
                        ...work[Number(id)],
                        state: 'conflict',
                        error: 'Mission absente ou affectation modifiée. Les preuves restent conservées.',
                        mission: {
                            ...work[Number(id)].mission,
                            property: {
                                ...work[Number(id)].mission.property,
                                access_code: null,
                            },
                        },
                    };
                }
            }
            const preparedMeta = {
                ...localMeta,
                preparedAt: new Date().toISOString(),
            };
            await writeVault([
                [
                    fresh.identity + ':bundle',
                    await encrypt(localKey, safeBundle),
                ],
                [fresh.identity + ':drafts', await encrypt(localKey, work)],
                [fresh.identity + ':meta', preparedMeta],
                ['current-meta', preparedMeta],
            ]);
            key.current = localKey;
            setMeta(preparedMeta);
            setBundle(safeBundle);
            draftRef.current = work;
            setDrafts(work);
            setDirty(false);
            setPin('');
            setReady(true);
            setSelected(
                fresh.missions[0]?.id ?? Number(Object.keys(work)[0]) ?? null,
            );
            await navigator.storage.persist().catch(() => false);
            setNote(
                fresh.missions.length +
                    ' missions préparées. Conservez votre code local ; seul cet appareil détient vos brouillons.',
            );
        } catch (error) {
            setNote(
                error instanceof Error
                    ? error.message
                    : 'Préparation impossible.',
            );
        } finally {
            setBusy(false);
        }
    }
    function edit(change: Partial<Draft>) {
        if (!selected || !current) return;
        const next = { ...drafts, [selected]: { ...current, ...change } };
        draftRef.current = next;
        setDrafts(next);
        setDirty(true);
    }
    async function saveCurrent() {
        setBusy(true);
        try {
            await save(draftRef.current);
            setNote('Brouillon enregistré sur cet appareil.');
        } catch {
            setNote(
                'Écriture locale impossible. Libérez de l’espace et réessayez ; ce travail n’est pas encore enregistré.',
            );
        } finally {
            setBusy(false);
        }
    }
    async function addPhotos(files: FileList | null, stage: string) {
        if (!files || !current) return;
        setBusy(true);
        try {
            if (current.photos.length + files.length > 20)
                throw new Error('20 photos maximum par compte rendu.');
            const photos: LocalPhoto[] = [];
            for (const file of Array.from(files)) {
                if (
                    file.size > 10 * 1024 * 1024 ||
                    !['image/jpeg', 'image/png', 'image/webp'].includes(
                        file.type,
                    )
                )
                    throw new Error(
                        'Photos JPEG, PNG ou WebP, 10 Mo maximum chacune.',
                    );
                photos.push({
                    id: operationId(),
                    name: file.name,
                    data: await fileAsDataUrl(file),
                    stage,
                    captured_at: null,
                });
            }
            await save({
                ...draftRef.current,
                [current.mission.id]: {
                    ...draftRef.current[current.mission.id],
                    photos: [...current.photos, ...photos],
                },
            });
            setNote('Photos et brouillon enregistrés sur cet appareil.');
        } catch (error) {
            setNote(
                error instanceof Error
                    ? error.message
                    : 'Photo non enregistrée : vérifiez l’espace disponible.',
            );
        } finally {
            setBusy(false);
        }
    }
    async function enqueue() {
        if (
            !current ||
            !current.notes.trim() ||
            current.answers.some((answer) => !answer)
        ) {
            setNote(
                'Complétez la checklist et les observations avant la soumission.',
            );
            return;
        }
        setBusy(true);
        try {
            await save({
                ...draftRef.current,
                [current.mission.id]: {
                    ...current,
                    state: 'pending',
                    error: undefined,
                },
            });
            setNote(
                'Compte rendu en attente d’envoi. Les données sont figées pour cette soumission.',
            );
        } catch {
            setNote(
                'Mise en attente impossible. Aucun succès d’enregistrement confirmé.',
            );
        } finally {
            setBusy(false);
        }
        if (navigator.onLine) void synchronize();
    }
    async function transmit(
        url: string,
        data: FormData,
        csrf: string,
    ): Promise<void> {
        const response = await fetch(url, {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
        });
        if (response.status === 409) throw new Error('CONFLICT');
        if (response.status === 401 || response.status === 419)
            throw new Error('SESSION');
        if (!response.ok) {
            let message = 'Échec d’envoi (' + response.status + ').';
            try {
                const json = (await response.json()) as {
                    message?: string;
                    errors?: Record<string, string[]>;
                };
                message = json.errors
                    ? Object.values(json.errors).flat().join(' ')
                    : (json.message ?? message);
            } catch {
                /* Keep response status. */
            }
            throw new Error(message);
        }
        const result = (await response.json()) as { received?: boolean };
        if (result.received !== true)
            throw new Error('Réception non confirmée.');
    }
    async function synchronize() {
        if (sending.current || !bundle || !key.current) return;
        sending.current = true;
        setBusy(true);
        try {
            await save(draftRef.current);
            const fresh = await fetchBundle();
            if (fresh.identity !== bundle.identity)
                throw new Error(
                    'Compte connecté différent : aucun envoi effectué. Reconnectez-vous au compte de ces preuves.',
                );
            if (!fresh.csrf) throw new Error('Session non disponible.');
            let work = { ...draftRef.current };
            for (const draft of Object.values(work)) {
                if (draft.state === 'received' || draft.state === 'conflict')
                    continue;
                try {
                    if (draft.incident.trim() && !draft.incidentReceived) {
                        const data = new FormData();
                        data.set('operation_id', draft.incidentOperation);
                        data.set('version', String(draft.mission.version));
                        data.set('description', draft.incident);
                        await transmit(
                            draft.mission.incident_url,
                            data,
                            fresh.csrf,
                        );
                        draft.incidentReceived = true;
                        work[draft.mission.id] = { ...draft };
                        await save(work);
                    }
                    if (draft.state === 'pending' || draft.state === 'error') {
                        const data = new FormData();
                        data.set('operation_id', draft.reportOperation);
                        data.set('version', String(draft.mission.version));
                        data.set('notes', draft.notes);
                        draft.answers.forEach((answer, i) =>
                            data.set('answers[' + i + ']', answer ? '1' : '0'),
                        );
                        draft.photos.forEach((photo) => {
                            data.append(
                                'photos[]',
                                dataUrlBlob(photo.data),
                                photo.name,
                            );
                            data.append('stages[]', photo.stage);
                            data.append('captured_at[]', '');
                        });
                        await transmit(
                            draft.mission.report_url,
                            data,
                            fresh.csrf,
                        );
                        work[draft.mission.id] = {
                            ...draft,
                            state: 'received',
                            error: undefined,
                            mission: {
                                ...draft.mission,
                                property: {
                                    ...draft.mission.property,
                                    access_code: null,
                                },
                            },
                        };
                        await save(work);
                    }
                } catch (error) {
                    const reason =
                        error instanceof Error
                            ? error.message
                            : 'Connexion interrompue.';
                    if (reason === 'SESSION')
                        throw new Error(
                            'Reconnectez-vous à votre compte. Aucun brouillon n’est supprimé.',
                        );
                    work[draft.mission.id] = {
                        ...draft,
                        state:
                            reason === 'CONFLICT'
                                ? 'conflict'
                                : draft.state === 'draft'
                                  ? 'draft'
                                  : 'error',
                        error:
                            reason === 'CONFLICT'
                                ? 'Mission modifiée : preuves à arbitrer avec le responsable.'
                                : reason,
                    };
                    if (reason === 'CONFLICT')
                        work[draft.mission.id].mission.property.access_code =
                            null;
                    await save(work);
                }
            }
            setNote(
                'Synchronisation terminée. Consultez les états : reçu, en attente ou à arbitrer.',
            );
        } catch (error) {
            setNote(
                error instanceof Error
                    ? error.message
                    : 'Connexion indisponible. Les preuves sont conservées.',
            );
        } finally {
            sending.current = false;
            setBusy(false);
        }
    }
    useEffect(() => {
        if (!online || !ready || !bundle) return;
        const timer = setTimeout(() => {
            if (
                !dirty &&
                Object.values(draftRef.current).some(
                    (d) =>
                        d.state === 'pending' ||
                        (d.incident && !d.incidentReceived),
                )
            )
                void synchronize();
        }, 500);
        return () => clearTimeout(timer);
    }, [online, ready, bundle, dirty]);
    async function selectAccount() {
        setBusy(true);
        try {
            const fresh = await fetchBundle();
            const selectedMeta = await readVault<VaultMeta>(
                fresh.identity + ':meta',
            );
            setMeta(selectedMeta ?? null);
            setPin('');
            setNote(
                selectedMeta
                    ? 'Retrouvez ce compte avec son code local.'
                    : 'Préparez ce compte avec un nouveau code local. Les preuves des autres comptes restent chiffrées sur cet appareil.',
            );
        } catch (error) {
            setNote(
                error instanceof Error
                    ? error.message
                    : 'Compte connecté non disponible.',
            );
        } finally {
            setBusy(false);
        }
    }
    function exportProofs() {
        if (!current) return;
        const data = {
            author: bundle?.user,
            concierge: bundle?.concierge,
            mission: {
                ...current.mission,
                property: { ...current.mission.property, access_code: null },
            },
            notes: current.notes,
            answers: current.answers,
            photos: current.photos,
            incident: current.incident,
            state: current.state,
        };
        const link = document.createElement('a');
        const url = URL.createObjectURL(
            new Blob([JSON.stringify(data)], { type: 'application/json' }),
        );
        link.href = url;
        link.download = 'preuves-mission-' + current.mission.id + '.json';
        link.click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    }
    function lock() {
        key.current = null;
        setBundle(null);
        setDrafts({});
        draftRef.current = {};
        setSelected(null);
        setReady(false);
        setNote(
            'Espace verrouillé. Les preuves chiffrées restent sur cet appareil.',
        );
    }
    const labels = {
        draft: 'Sur cet appareil',
        pending: 'En attente d’envoi',
        received: 'Reçu par le serveur',
        conflict: 'À arbitrer',
        error: 'Échec à reprendre',
    };
    return (
        <div className="brand-surface terrain-app">
            <header className="terrain-app-header">
                <a
                    href={dashboard.url()}
                    aria-label="Retour au tableau de bord"
                >
                    <ArrowLeft size={20} />
                </a>
                <Brand compact name={bundle?.app_name} />
                <span
                    className={
                        'terrain-connection ' + (online ? '' : 'offline')
                    }
                >
                    {online ? <Wifi size={14} /> : <WifiOff size={14} />}{' '}
                    {online ? 'Connexion réseau' : 'Hors connexion'}
                </span>
            </header>
            <main className="terrain-app-main">
                <div className="terrain-app-heading">
                    <div>
                        <span className="section-kicker">
                            Votre journée, avec vous
                        </span>
                        <h1>Mes missions</h1>
                        <p>
                            {bundle
                                ? bundle.concierge + ' · ' + bundle.user.name
                                : 'Préparez vos missions avant de partir.'}
                        </p>
                    </div>
                    {ready ? (
                        <button
                            aria-label="Verrouiller les preuves locales"
                            onClick={lock}
                            disabled={busy || dirty}
                        >
                            <LockKeyhole size={19} />
                        </button>
                    ) : null}
                </div>
                {note ? (
                    <div className="terrain-notice" role="status">
                        {note}
                    </div>
                ) : null}
                {!secure ? (
                    <div className="terrain-security">
                        <LockKeyhole />
                        <b>
                            Une connexion sécurisée pour préparer le hors
                            connexion.
                        </b>
                        <p>
                            Utilisez HTTPS avec le certificat Laragon reconnu
                            par votre navigateur, ou une URL localhost. Votre
                            travail local sera chiffré et protégé par votre
                            code.
                        </p>
                        <a href={login.url()}>Retour à la connexion</a>
                    </div>
                ) : !ready ? (
                    <section className="terrain-unlock">
                        <span className="terrain-large-icon">
                            <Download size={27} />
                        </span>
                        <h2>
                            {meta
                                ? 'Retrouvez votre journée'
                                : 'Préparez votre journée'}
                        </h2>
                        <p>
                            {meta
                                ? 'Déverrouillez les missions et les preuves préparées sur cet appareil avec votre code local.'
                                : 'Acceptez vos missions dans le tableau de bord, puis choisissez un code local personnel pour les emporter.'}
                        </p>
                        <label className="form-field">
                            {meta
                                ? 'Code local'
                                : 'Choisir un code local (8 caractères minimum)'}
                            <input
                                type="password"
                                value={pin}
                                onChange={(e) => setPin(e.target.value)}
                                autoComplete="off"
                                minLength={8}
                            />
                        </label>
                        <div className="mt-5 flex flex-col gap-3">
                            {meta ? (
                                <button
                                    className="brand-button"
                                    disabled={busy}
                                    onClick={() => void unlock()}
                                >
                                    <LockKeyhole size={16} />
                                    Déverrouiller
                                </button>
                            ) : (
                                <button
                                    className="brand-button"
                                    disabled={busy || !online}
                                    onClick={() => void prepare()}
                                >
                                    <Download size={16} />
                                    Préparer mes missions
                                </button>
                            )}
                        </div>
                        {online ? (
                            <button
                                className="mt-4 text-xs text-teal"
                                disabled={busy}
                                onClick={() => void selectAccount()}
                            >
                                Utiliser le compte actuellement connecté
                            </button>
                        ) : null}
                        <p className="form-hint mt-4">
                            Appareil personnel recommandé. Gardez ce code : sans
                            lui, les fichiers chiffrés ne peuvent pas être
                            relus. Aucune preuve en attente n’est purgée
                            automatiquement.
                        </p>
                    </section>
                ) : (
                    <>
                        <div className="terrain-actions">
                            <button
                                className="brand-button small secondary"
                                disabled={busy || !online || dirty}
                                onClick={() => void prepare()}
                            >
                                <Download size={14} />
                                Actualiser la journée
                            </button>
                            <button
                                className="brand-button small"
                                disabled={busy || !online}
                                onClick={() => void synchronize()}
                            >
                                <RefreshCw
                                    size={14}
                                    className={busy ? 'animate-spin' : ''}
                                />
                                Synchroniser
                            </button>
                        </div>
                        <p className="form-hint mb-5">
                            Préparée le{' '}
                            {meta
                                ? new Date(meta.preparedAt).toLocaleString(
                                      'fr-FR',
                                  )
                                : ''}
                            .{' '}
                            {
                                Object.values(drafts).filter((d) =>
                                    ['pending', 'error', 'conflict'].includes(
                                        d.state,
                                    ),
                                ).length
                            }{' '}
                            comptes rendus à envoyer ou arbitrer.
                        </p>
                        <div className="terrain-app-grid">
                            <div className="terrain-mission-list">
                                {Object.values(drafts).length ? (
                                    Object.values(drafts).map((d) => (
                                        <button
                                            key={d.mission.id}
                                            className={
                                                'terrain-mission-card ' +
                                                (selected === d.mission.id
                                                    ? 'active'
                                                    : '')
                                            }
                                            disabled={dirty || busy}
                                            onClick={() =>
                                                setSelected(d.mission.id)
                                            }
                                        >
                                            <span>
                                                {new Date(
                                                    d.mission.scheduled_at,
                                                ).toLocaleString('fr-FR', {
                                                    day: 'numeric',
                                                    month: 'short',
                                                    hour: '2-digit',
                                                    minute: '2-digit',
                                                })}
                                            </span>
                                            <b>{d.mission.title}</b>
                                            <p>{d.mission.property.name}</p>
                                            <small
                                                className={
                                                    d.state === 'conflict'
                                                        ? 'text-red-700'
                                                        : ''
                                                }
                                            >
                                                {labels[d.state]}
                                            </small>
                                        </button>
                                    ))
                                ) : (
                                    <div className="terrain-notice">
                                        Aucune mission acceptée à préparer.
                                        Acceptez votre affectation dans le
                                        tableau de bord.
                                    </div>
                                )}
                            </div>
                            {current ? (
                                <section className="terrain-work-card">
                                    <span className="status-chip green">
                                        {labels[current.state]}
                                    </span>
                                    <h2>{current.mission.title}</h2>
                                    <p>
                                        {current.mission.property.name} ·{' '}
                                        {current.mission.property.address}
                                    </p>
                                    {current.error ? (
                                        <div className="form-errors">
                                            {current.error}
                                        </div>
                                    ) : null}
                                    {current.mission.feedback ? (
                                        <div className="form-errors">
                                            Reprise demandée :{' '}
                                            {current.mission.feedback}
                                        </div>
                                    ) : null}
                                    <div className="detail-block">
                                        <h3>Consignes</h3>
                                        {current.mission.instructions ??
                                            current.mission.property
                                                .instructions ??
                                            'Aucune consigne complémentaire.'}
                                        {current.mission.property
                                            .access_code ? (
                                            <p className="mt-3">
                                                <b>Accès autorisé :</b>{' '}
                                                {
                                                    current.mission.property
                                                        .access_code
                                                }
                                            </p>
                                        ) : null}
                                    </div>
                                    <fieldset
                                        disabled={
                                            current.state !== 'draft' || busy
                                        }
                                    >
                                        <legend className="mb-4 text-sm font-semibold">
                                            Checklist de la mission
                                        </legend>
                                        <div className="checklist-items">
                                            {current.mission.checklist.map(
                                                (item, i) => (
                                                    <label key={i}>
                                                        <input
                                                            type="checkbox"
                                                            checked={
                                                                current.answers[
                                                                    i
                                                                ] ?? false
                                                            }
                                                            onChange={(e) =>
                                                                edit({
                                                                    answers:
                                                                        current.answers.map(
                                                                            (
                                                                                v,
                                                                                j,
                                                                            ) =>
                                                                                i ===
                                                                                j
                                                                                    ? e
                                                                                          .target
                                                                                          .checked
                                                                                    : v,
                                                                        ),
                                                                })
                                                            }
                                                        />
                                                        {item}
                                                    </label>
                                                ),
                                            )}
                                        </div>
                                        <label className="form-field mt-5">
                                            Observations et compte rendu
                                            <textarea
                                                value={current.notes}
                                                onChange={(e) =>
                                                    edit({
                                                        notes: e.target.value,
                                                    })
                                                }
                                                placeholder="Votre compte rendu…"
                                            />
                                        </label>
                                        <div className="terrain-photo-buttons">
                                            <label>
                                                <Camera size={18} />
                                                Photos avant
                                                <input
                                                    type="file"
                                                    accept="image/jpeg,image/png,image/webp"
                                                    multiple
                                                    onChange={(e) =>
                                                        void addPhotos(
                                                            e.target.files,
                                                            'before',
                                                        )
                                                    }
                                                />
                                            </label>
                                            <label>
                                                <Camera size={18} />
                                                {current.mission.kind ===
                                                'service'
                                                    ? 'Photos après'
                                                    : 'Photos de constat'}
                                                <input
                                                    type="file"
                                                    accept="image/jpeg,image/png,image/webp"
                                                    multiple
                                                    onChange={(e) =>
                                                        void addPhotos(
                                                            e.target.files,
                                                            current.mission
                                                                .kind ===
                                                                'service'
                                                                ? 'after'
                                                                : 'observation',
                                                        )
                                                    }
                                                />
                                            </label>
                                        </div>
                                        <div className="report-gallery">
                                            {current.photos.map((photo) => (
                                                <div key={photo.id}>
                                                    <img
                                                        src={photo.data}
                                                        alt="Preuve enregistrée localement"
                                                    />
                                                    <span>
                                                        {photo.stage ===
                                                        'before'
                                                            ? 'Avant'
                                                            : photo.stage ===
                                                                'after'
                                                              ? 'Après'
                                                              : 'Constat'}{' '}
                                                        · Date de capture
                                                        inconnue
                                                    </span>
                                                </div>
                                            ))}
                                        </div>
                                        <label className="form-field mt-5">
                                            Incident à signaler au responsable
                                            <textarea
                                                value={current.incident}
                                                disabled={
                                                    current.incidentReceived
                                                }
                                                onChange={(e) =>
                                                    edit({
                                                        incident:
                                                            e.target.value,
                                                    })
                                                }
                                                placeholder="Casse, anomalie, élément manquant…"
                                            />
                                        </label>
                                        <p className="form-hint mt-2">
                                            Le responsable reçoit le signalement
                                            après son envoi, avant la validation
                                            du compte rendu.
                                        </p>
                                    </fieldset>
                                    {current.state === 'draft' ? (
                                        <div className="terrain-work-footer">
                                            <button
                                                className="brand-button small secondary"
                                                disabled={busy}
                                                onClick={() =>
                                                    void saveCurrent()
                                                }
                                            >
                                                <Save size={15} />
                                                Enregistrer le brouillon
                                            </button>
                                            <button
                                                className="brand-button small"
                                                disabled={busy}
                                                onClick={() => void enqueue()}
                                            >
                                                <Check size={15} />
                                                Soumettre
                                            </button>
                                            {current.incident.trim() &&
                                            !current.incidentReceived ? (
                                                <button
                                                    className="brand-button small secondary"
                                                    disabled={busy || !online}
                                                    onClick={() =>
                                                        void synchronize()
                                                    }
                                                >
                                                    <TriangleAlert size={15} />
                                                    Envoyer le signalement
                                                </button>
                                            ) : null}
                                        </div>
                                    ) : null}
                                    {dirty ? (
                                        <p className="mt-4 text-xs text-amber-700">
                                            Modifications à enregistrer sur cet
                                            appareil.
                                        </p>
                                    ) : (
                                        <p className="mt-4 text-xs text-teal">
                                            Enregistré localement ·{' '}
                                            {current.photos.length} fichiers
                                            conservés.
                                        </p>
                                    )}
                                    {current.state === 'conflict' ? (
                                        <button
                                            className="brand-button small secondary mt-4"
                                            onClick={exportProofs}
                                        >
                                            Exporter les preuves pour le
                                            responsable
                                        </button>
                                    ) : null}
                                    {current.state === 'conflict' ? (
                                        <p className="form-hint mt-4">
                                            La soumission est bloquée. Vos
                                            observations et vos fichiers restent
                                            disponibles ici pour le responsable
                                            ; ils ne sont pas rattachés
                                            automatiquement à une autre mission.
                                        </p>
                                    ) : null}
                                </section>
                            ) : null}
                        </div>
                    </>
                )}
            </main>
        </div>
    );
}
createRoot(document.getElementById('terrain-root')!).render(<Terrain />);
