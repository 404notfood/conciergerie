import { Head, Link } from '@inertiajs/react';
import { LockKeyhole } from 'lucide-react';
import { InertiaBrand as Brand } from '@/components/concierge/brand';
import { logout } from '@/routes';
export default function AccessBlocked() {
    return (
        <div className="brand-surface setup-page">
            <Head title="Accès temporairement indisponible" />
            <section className="setup-card">
                <Brand />
                <LockKeyhole className="mt-8 size-8 text-teal" />
                <h1>L’espace est temporairement indisponible.</h1>
                <p>
                    Contactez le responsable de votre conciergerie. Votre
                    travail enregistré et vos preuves locales restent conservés.
                </p>
                <Link
                    className="brand-button small"
                    href={logout()}
                    as="button"
                >
                    Me déconnecter
                </Link>
            </section>
        </div>
    );
}
