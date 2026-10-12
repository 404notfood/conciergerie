import AppLogoIcon from '@/components/app-logo-icon';
import { usePage } from '@inertiajs/react';
export function InertiaBrand({ compact = false }: { compact?: boolean }) {
    return <Brand compact={compact} name={usePage().props.name} />;
}
export function Brand({
    compact = false,
    name = import.meta.env.VITE_APP_NAME || 'Tout en Main',
}: {
    compact?: boolean;
    name?: string;
}) {
    return (
        <span className="brand flex items-center gap-3">
            <AppLogoIcon className={compact ? 'size-6' : 'size-9'} />
            <span
                className={
                    compact
                        ? 'text-xs font-bold tracking-tight'
                        : 'text-xl font-bold tracking-tight'
                }
            >
                {name}
                <span className="brand-dot">.</span>
            </span>
        </span>
    );
}
export function RoomIllustration({ after = false }: { after?: boolean }) {
    return (
        <svg
            viewBox="0 0 180 130"
            role="img"
            aria-label={
                after
                    ? 'Illustration d’un salon après entretien'
                    : 'Illustration d’un salon avant entretien'
            }
            className="room-illustration"
        >
            <rect width="180" height="130" fill="#ede7dc" />
            <path d="M0 93h180v37H0z" fill="#c8b8a0" />
            <path d="m120 0-8 93H0V0" fill="#f5f0e8" />
            <rect x="115" y="12" width="55" height="78" fill="#687d77" />
            <rect x="119" y="16" width="46" height="71" fill="#c3dcd4" />
            <path d="M142 16v71M119 53h46" stroke="#fff" strokeWidth="3" />
            <rect
                x="12"
                y="56"
                width="88"
                height="40"
                rx="7"
                fill={after ? '#f8f5ec' : '#b4b7b0'}
            />
            <rect
                x="18"
                y="47"
                width="76"
                height="28"
                rx="7"
                fill={after ? '#ded7c8' : '#a9ada6'}
            />
            <rect x="22" y="54" width="24" height="18" rx="4" fill="#8a9e8e" />
            <rect x="56" y="54" width="25" height="18" rx="4" fill="#efeadf" />
            <rect x="25" y="96" width="60" height="3" fill="#78644c" />
            <ellipse cx="100" cy="113" rx="37" ry="10" fill="#eee6d8" />
            <ellipse cx="100" cy="99" rx="23" ry="9" fill="#897559" />
            <path d="M85 104v13m29-13v13" stroke="#625441" strokeWidth="3" />
            <rect x="153" y="89" width="16" height="20" rx="3" fill="#9c8062" />
            <path
                d="M162 91V64m0 14c-17-19-18-3 0 6m0-15c16-17 18-2 0 7"
                stroke="#497859"
                strokeWidth="4"
                fill="none"
            />
            {after ? (
                <>
                    <ellipse cx="100" cy="98" rx="6" ry="3" fill="#f9f3e7" />
                    <path
                        d="M100 96v-8m0 3-5-3m5 1 5-4"
                        stroke="#49816c"
                        strokeWidth="2"
                    />
                </>
            ) : (
                <>
                    <path d="m55 65 21 20-12 6-20-25" fill="#707f7b" />
                    <rect x="98" y="95" width="16" height="4" fill="#dad5cc" />
                </>
            )}
        </svg>
    );
}
