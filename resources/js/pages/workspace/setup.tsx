import { Head, Link } from '@inertiajs/react';
import { Brand } from '@/components/concierge/brand';
import { BusinessForm, Field } from '@/components/concierge/business-form';
import { store } from '@/routes/workspace';
import { logout } from '@/routes';
export default function Setup() {
    return (
        <div className="brand-surface setup-page">
            <Head title="Créer ma conciergerie" />
            <div className="setup-card">
                <Brand />
                <h1>Bienvenue dans votre nouvel espace.</h1>
                <p>
                    Commencez par donner un nom à votre conciergerie. Vous
                    pourrez ensuite ajouter vos clients, vos lieux
                    d’intervention et votre équipe.
                </p>
                <BusinessForm action={store.url()} submit="Créer mon espace">
                    <Field
                        name="name"
                        label="Nom de votre conciergerie"
                        placeholder="Ex. Les Clés du Lac"
                        required
                    />
                    <p className="form-hint">
                        Votre espace est privé. Les comptes clients et
                        intervenants seront créés par votre conciergerie.
                    </p>
                </BusinessForm>
                <Link
                    href={logout()}
                    className="mt-6 block text-center text-xs text-slate-500"
                    as="button"
                >
                    Me déconnecter
                </Link>
            </div>
        </div>
    );
}
