import { Link } from '@inertiajs/react';
import { InertiaBrand as Brand } from '@/components/concierge/brand';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';
export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="brand-surface setup-page">
            <div className="w-full max-w-md">
                <Link
                    href={home()}
                    className="mb-8 flex justify-center"
                    aria-label="Retour à l’accueil"
                >
                    <Brand />
                </Link>
                <div className="auth-brand-card">
                    <div className="mb-7 text-center">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {title}
                        </h1>
                        <p className="mt-3 text-xs leading-6 text-slate-500">
                            {description}
                        </p>
                    </div>
                    {children}
                </div>
                <p className="mt-6 text-center text-xs text-slate-500">
                    Votre conciergerie. Vos équipes. Un seul espace.
                </p>
            </div>
        </div>
    );
}
