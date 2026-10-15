import { useState } from 'react';
import { Archive, Pencil, RotateCcw } from 'lucide-react';
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
import { archive, update } from '@/routes/directory';
import type {
    ClientRecord,
    PropertyRecord,
    StayRecord,
    TemplateRecord,
} from '@/types/concierge';

type Entry =
    | { resource: 'clients'; record: ClientRecord }
    | { resource: 'properties'; record: PropertyRecord }
    | { resource: 'templates'; record: TemplateRecord }
    | { resource: 'stays'; record: StayRecord };

export function ArchiveFilter({
    archived,
    onChange,
}: {
    archived: boolean;
    onChange: (value: boolean) => void;
}) {
    return (
        <label className="col-span-full mb-4 flex items-center gap-2 text-xs">
            <input
                type="checkbox"
                checked={archived}
                onChange={(event) => onChange(event.target.checked)}
            />
            Afficher les fiches archivées
        </label>
    );
}

export function DirectoryActions(entry: Entry) {
    const [mode, setMode] = useState<'edit' | 'archive' | null>(null);
    const { resource, record } = entry;
    const archived = Boolean(record.archived_at);
    const name = 'name' in record ? record.name : record.title;
    return (
        <>
            <div className="mt-4 flex flex-wrap items-center gap-3 text-xs font-semibold text-teal">
                {archived ? (
                    <span className="status-chip gray">Archivé</span>
                ) : null}
                <button
                    type="button"
                    className="inline-flex items-center gap-1"
                    onClick={() => setMode('edit')}
                    aria-label={`Modifier ${name}`}
                >
                    <Pencil size={13} /> Modifier
                </button>
                <button
                    type="button"
                    className="inline-flex items-center gap-1"
                    onClick={() => setMode('archive')}
                    aria-label={`${archived ? 'Restaurer' : 'Archiver'} ${name}`}
                >
                    {archived ? <RotateCcw size={13} /> : <Archive size={13} />}
                    {archived ? 'Restaurer' : 'Archiver'}
                </button>
            </div>
            <Dialog
                open={mode !== null}
                onOpenChange={(open) => {
                    if (!open) setMode(null);
                }}
            >
                <DialogContent className="business-dialog">
                    <DialogHeader>
                        <DialogTitle>
                            {mode === 'edit'
                                ? 'Modifier'
                                : archived
                                  ? 'Restaurer'
                                  : 'Archiver'}{' '}
                            · {name}
                        </DialogTitle>
                        <DialogDescription>
                            {mode === 'edit'
                                ? 'Mettez à jour les informations de cette fiche.'
                                : archived
                                  ? 'La fiche sera de nouveau disponible pour les prochaines opérations.'
                                  : 'La fiche sera retirée des choix de création. Les missions et rapports existants seront conservés.'}
                        </DialogDescription>
                    </DialogHeader>
                    {mode === 'archive' ? (
                        <BusinessForm
                            action={archive.url({
                                resource,
                                record: record.id,
                            })}
                            submit={
                                archived
                                    ? 'Restaurer la fiche'
                                    : 'Archiver la fiche'
                            }
                            onSuccess={() => setMode(null)}
                        >
                            <input
                                type="hidden"
                                name="archived"
                                value={archived ? '0' : '1'}
                            />
                        </BusinessForm>
                    ) : mode === 'edit' ? (
                        <BusinessForm
                            action={update.url({ resource, record: record.id })}
                            onSuccess={() => setMode(null)}
                            transform={(data) => {
                                const values = data as Record<string, unknown>;
                                return {
                                    ...data,
                                    ...(resource === 'templates'
                                        ? {
                                              checklist:
                                                  (typeof values.checklist ===
                                                  'string'
                                                      ? values.checklist
                                                      : ''
                                                  )
                                                      .split(/\r?\n/)
                                                      .map((line) =>
                                                          line.trim(),
                                                      )
                                                      .filter(Boolean),
                                          }
                                        : {}),
                                    ...(resource === 'properties'
                                        ? {
                                              offline_access:
                                                  values.offline_access === '1',
                                              clear_access_code:
                                                  values.clear_access_code ===
                                                  '1',
                                          }
                                        : {}),
                                };
                            }}
                        >
                            {resource === 'clients' ? (
                                <>
                                    <Field
                                        label="Nom"
                                        name="name"
                                        defaultValue={record.name}
                                        required
                                    />
                                    <Field
                                        label="E-mail"
                                        name="email"
                                        type="email"
                                        defaultValue={record.email}
                                        required
                                    />
                                    <Field
                                        label="Téléphone"
                                        name="phone"
                                        defaultValue={record.phone ?? ''}
                                    />
                                    <SelectField
                                        label="Type de client"
                                        name="kind"
                                        defaultValue={record.kind}
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
                                        required
                                        empty=""
                                    />
                                </>
                            ) : null}
                            {resource === 'properties' ? (
                                <>
                                    <Field
                                        label="Nom du lieu"
                                        name="name"
                                        defaultValue={record.name}
                                        required
                                    />
                                    <Field
                                        label="Adresse"
                                        name="address"
                                        defaultValue={record.address}
                                        required
                                    />
                                    <SelectField
                                        label="Type de lieu"
                                        name="kind"
                                        defaultValue={record.kind}
                                        items={[
                                            {
                                                value: 'rental',
                                                label: 'Location saisonnière',
                                            },
                                            {
                                                value: 'residence',
                                                label: 'Résidence',
                                            },
                                            {
                                                value: 'business',
                                                label: 'Entreprise',
                                            },
                                            {
                                                value: 'other',
                                                label: 'Autre lieu',
                                            },
                                        ]}
                                        required
                                        empty=""
                                    />
                                    <TextArea
                                        label="Consignes"
                                        name="instructions"
                                        defaultValue={record.instructions ?? ''}
                                    />
                                    <Field
                                        label="Nouveau code d’accès"
                                        name="access_code"
                                        type="password"
                                        placeholder="Laisser vide pour conserver le code actuel"
                                    />
                                    <label className="flex items-center gap-2 text-xs">
                                        <input
                                            type="checkbox"
                                            name="clear_access_code"
                                            value="1"
                                        />{' '}
                                        Supprimer le code d’accès actuel
                                    </label>
                                    <label className="flex items-center gap-2 text-xs">
                                        <input
                                            type="checkbox"
                                            name="offline_access"
                                            value="1"
                                            defaultChecked={
                                                record.offline_access
                                            }
                                        />{' '}
                                        Autoriser le code d’accès en mode
                                        terrain hors ligne
                                    </label>
                                    <p className="form-hint">
                                        Le client de rattachement et
                                        l’historique restent associés à ce lieu.
                                    </p>
                                </>
                            ) : null}
                            {resource === 'templates' ? (
                                <>
                                    <Field
                                        label="Nom de la prestation"
                                        name="name"
                                        defaultValue={record.name}
                                        required
                                    />
                                    <TextArea
                                        label="Checklist — une tâche par ligne"
                                        name="checklist"
                                        defaultValue={record.checklist.join(
                                            '\n',
                                        )}
                                        required
                                    />
                                    <p className="form-hint">
                                        La nouvelle checklist s’appliquera aux
                                        prochaines missions. Les missions et
                                        propositions déjà préparées conservent
                                        leur checklist.
                                    </p>
                                </>
                            ) : null}
                            {resource === 'stays' ? (
                                <>
                                    <Field
                                        label="Titre"
                                        name="title"
                                        defaultValue={record.title}
                                        required
                                    />
                                    <Field
                                        label="Début"
                                        name="starts_on"
                                        type="date"
                                        defaultValue={record.starts_on.slice(
                                            0,
                                            10,
                                        )}
                                        required
                                    />
                                    <Field
                                        label="Fin"
                                        name="ends_on"
                                        type="date"
                                        defaultValue={record.ends_on.slice(
                                            0,
                                            10,
                                        )}
                                        required
                                    />
                                    <SelectField
                                        label="Nature de la période"
                                        name="kind"
                                        defaultValue={record.kind}
                                        items={[
                                            { value: 'stay', label: 'Séjour' },
                                            {
                                                value: 'blocked',
                                                label: 'Indisponibilité',
                                            },
                                            {
                                                value: 'unclassified',
                                                label: 'À qualifier',
                                            },
                                        ]}
                                        required
                                        empty=""
                                    />
                                    {record.source !== 'manual' ? (
                                        <p className="form-hint">
                                            Un nouvel import du calendrier
                                            source peut actualiser les dates et
                                            la nature de cette période.
                                        </p>
                                    ) : null}
                                </>
                            ) : null}
                        </BusinessForm>
                    ) : null}
                </DialogContent>
            </Dialog>
        </>
    );
}
