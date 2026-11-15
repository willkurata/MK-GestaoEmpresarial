import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import SiteLayout from '@/layouts/SiteLayout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
const isPublicSite =
    typeof document !== 'undefined' && document.body.classList.contains('site');
const titleSuffix = isPublicSite ? 'MK Negócios' : appName;

void createInertiaApp({
    title: (title) => (title ? `${title} - ${titleSuffix}` : titleSuffix),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
                return null;
            case name.startsWith('site/'):
                return SiteLayout;
            case name === 'company/Portal':
                return AppLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    withApp: (app) => {
        app.directive('focus', {
            mounted: (el: HTMLElement, shouldFocus) => {
                if (shouldFocus.value !== false) {
                    el.focus();
                }
            },
        });
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load (the public site is light-only)...
if (!isPublicSite) {
    initializeTheme();
}

// This will listen for flash toast data from the server...
initializeFlashToast();
