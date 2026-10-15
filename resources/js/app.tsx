import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'ConcierPilot';

void createInertiaApp({
    title: (title) => title || appName,
    layout: (name) => {
        switch (true) {
            case name === 'welcome' ||
                name === 'pricing' ||
                name.startsWith('marketing/') ||
                name.startsWith('workspace/') ||
                name.startsWith('administration/'):
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#3155b9',
    },
});

// This will set light / dark mode on load...
initializeTheme();
