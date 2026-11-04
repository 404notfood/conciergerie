import { useState } from 'react';
import { Form } from '@inertiajs/react';
import { RefreshCw, Plus } from 'lucide-react';
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
import { store, update, sync } from '@/routes/calendar-feeds';
import type { CalendarFeedRecord, PropertyRecord } from '@/types/concierge';

function timestamp(value: string | null): string {
    return value
        ? new Date(value).toLocaleString('fr-FR', { timeZone: 'Europe/Paris' })
        : 'Aucune synchronisation réussie';
}

export function CalendarFeeds({
    feeds,
    properties,
}: {
    feeds: CalendarFeedRecord[];
    properties: PropertyRecord[];
}) {
    const [open, setOpen] = useState(false);
    const [selected, setSelected] = useState<CalendarFeedRecord | null>(null);
    const propertyOptions = properties
        .filter((property) => !property.archived_at)
        .map((property) => ({ value: property.id, label: property.name }));

    function edit(feed: CalendarFeedRecord | null) {
        setSelected(feed);
        setOpen(true);
    }

    return (
        <section className="workspace-card">
            <div className="workspace-card-heading">
                <h2>Calendriers connectés</h2>
                <button
                    onClick={() => edit(null)}
                    disabled={!propertyOptions.length}
                >
                    <Plus size={15} /> Connecter un calendrier
                </button>
            </div>
            <p className="form-hint">
                Mise à jour environ toutes les 15 minutes selon votre abonnement
                et la disponibilité du fournisseur. Les nouvelles périodes
                restent à qualifier. Aucune mission n’est créée ou modifiée
                automatiquement.
            </p>
            {!feeds.length ? (
                <p className="form-hint">
                    {propertyOptions.length
                        ? 'Connectez le lien iCal de votre logement depuis Airbnb, Booking ou un autre fournisseur.'
                        : 'Ajoutez un lieu pour connecter son calendrier.'}
                </p>
            ) : null}
            {feeds.map((feed) => (
                <article
                    className="border-b border-border py-4 last:border-0"
                    key={feed.id}
                >
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 className="font-medium">{feed.name}</h3>
                            <p className="text-sm">
                                {feed.property.name} ·{' '}
                                {feed.source === 'ical'
                                    ? 'iCal'
                                    : feed.source === 'airbnb'
                                      ? 'Airbnb'
                                      : 'Booking'}
                            </p>
                        </div>
                        <span
                            className={
                                'status-chip ' +
                                (!feed.is_active || feed.property.archived_at
                                    ? 'amber'
                                    : feed.last_error
                                      ? 'red'
                                      : 'blue')
                            }
                        >
                            {!feed.is_active
                                ? 'Suspendu'
                                : feed.property.archived_at
                                  ? 'Lieu archivé'
                                  : feed.last_error
                                    ? 'À vérifier'
                                    : feed.last_synced_at
                                      ? 'Connecté'
                                      : 'En attente'}
                        </span>
                    </div>
                    <p className="form-hint">
                        Dernier succès : {timestamp(feed.last_synced_at)} ·{' '}
                        {feed.event_count} période(s)
                    </p>
                    {feed.last_error ? (
                        <p className="form-errors" role="alert">
                            {feed.last_error}
                        </p>
                    ) : null}
                    <div className="mt-3 flex flex-wrap items-start gap-3">
                        <button
                            className="brand-button small secondary"
                            onClick={() => edit(feed)}
                        >
                            Modifier / suspendre
                        </button>
                        {feed.is_active && !feed.property.archived_at ? (
                            <Form
                                action={sync.url(feed.id)}
                                method="post"
                                options={{ preserveScroll: true }}
                                disableWhileProcessing
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <button
                                            className="brand-button small secondary"
                                            disabled={processing}
                                            type="submit"
                                        >
                                            <RefreshCw
                                                size={14}
                                                className={
                                                    processing
                                                        ? 'animate-spin'
                                                        : ''
                                                }
                                            />{' '}
                                            {processing
                                                ? 'Synchronisation…'
                                                : 'Synchroniser maintenant'}
                                        </button>
                                        {Object.entries(errors).map(
                                            ([key, message]) => (
                                                <p
                                                    className="form-errors mt-2"
                                                    role="alert"
                                                    key={key}
                                                >
                                                    {String(message)}
                                                </p>
                                            ),
                                        )}
                                    </>
                                )}
                            </Form>
                        ) : null}
                    </div>
                </article>
            ))}
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="business-dialog">
                    <DialogHeader>
                        <DialogTitle>
                            {selected
                                ? 'Modifier le calendrier'
                                : 'Connecter un calendrier iCal'}
                        </DialogTitle>
                        <DialogDescription>
                            {selected
                                ? 'Vous pouvez remplacer le lien ou suspendre les mises à jour. Les périodes déjà importées sont conservées.'
                                : 'Copiez le lien d’export iCal du logement depuis votre plateforme de réservation.'}
                        </DialogDescription>
                    </DialogHeader>
                    <BusinessForm
                        key={selected?.id ?? 'new'}
                        action={
                            selected ? update.url(selected.id) : store.url()
                        }
                        submit={
                            selected
                                ? 'Enregistrer'
                                : 'Connecter et synchroniser'
                        }
                        onSuccess={() => setOpen(false)}
                    >
                        <Field
                            label="Nom du calendrier"
                            name="name"
                            defaultValue={selected?.name}
                            required
                            placeholder="Airbnb — Appartement centre"
                        />
                        {!selected ? (
                            <>
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
                            </>
                        ) : null}
                        <Field
                            label={
                                selected
                                    ? 'Nouveau lien iCal (laisser vide pour conserver le lien actuel)'
                                    : 'Lien iCal HTTPS'
                            }
                            name="url"
                            required={!selected}
                            placeholder="https://…"
                        />
                        {selected ? (
                            <SelectField
                                label="Mises à jour automatiques"
                                name="is_active"
                                defaultValue={selected.is_active ? '1' : '0'}
                                empty=""
                                items={[
                                    { value: '1', label: 'Activées' },
                                    { value: '0', label: 'Suspendues' },
                                ]}
                                required
                            />
                        ) : null}
                        <p className="form-hint">
                            Le lien est conservé de manière privée. Les périodes
                            retirées ou annulées dans le flux sont archivées ;
                            celles qui réapparaissent sont restaurées. Le titre
                            et les dates suivent le calendrier source. La
                            qualification des périodes est conservée.
                        </p>
                    </BusinessForm>
                </DialogContent>
            </Dialog>
        </section>
    );
}
