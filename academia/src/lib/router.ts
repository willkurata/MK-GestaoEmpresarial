import type { Component } from 'vue';
import { computed, defineAsyncComponent, ref } from 'vue';

/**
 * Roteador por hash, sem dependência extra. Hash funciona em qualquer
 * hospedagem estática e no modo kiosk (catraca/totem) sem configurar o
 * servidor para reescrever URLs.
 */
type RouteDefinition = {
    path: string;
    component: Component;
    title: string;
};

const page = (loader: () => Promise<{ default: Component }>): Component =>
    defineAsyncComponent(loader);

export const routes: RouteDefinition[] = [
    {
        path: '/',
        title: 'Início',
        component: page(() => import('../pages/Launcher.vue')),
    },
    {
        path: '/catraca',
        title: 'Catraca',
        component: page(() => import('../pages/turnstile/TurnstileScreen.vue')),
    },
    {
        path: '/totem',
        title: 'Totem',
        component: page(() => import('../pages/kiosk/KioskHome.vue')),
    },
    {
        path: '/totem/checkin',
        title: 'Totem · Check-in',
        component: page(() => import('../pages/kiosk/KioskCheckIn.vue')),
    },
    {
        path: '/totem/pagar',
        title: 'Totem · Pagar',
        component: page(() => import('../pages/kiosk/KioskPayment.vue')),
    },
    {
        path: '/totem/matricula',
        title: 'Totem · Matrícula',
        component: page(() => import('../pages/kiosk/KioskEnroll.vue')),
    },
    {
        path: '/totem/aulas',
        title: 'Totem · Aulas',
        component: page(() => import('../pages/kiosk/KioskClasses.vue')),
    },
    {
        path: '/aluno',
        title: 'Aluno',
        component: page(() => import('../pages/student/StudentHome.vue')),
    },
    {
        path: '/aluno/aulas',
        title: 'Aulas',
        component: page(() => import('../pages/student/StudentClasses.vue')),
    },
    {
        path: '/aluno/treinos',
        title: 'Treinos',
        component: page(() => import('../pages/student/StudentHistory.vue')),
    },
    {
        path: '/aluno/avisos',
        title: 'Avisos',
        component: page(
            () => import('../pages/student/StudentNotifications.vue'),
        ),
    },
    {
        path: '/aluno/pagamento',
        title: 'Pagamento',
        component: page(() => import('../pages/student/StudentPayment.vue')),
    },
    {
        path: '/admin',
        title: 'Visão geral',
        component: page(() => import('../pages/admin/AdminDashboard.vue')),
    },
    {
        path: '/admin/alunos',
        title: 'Alunos',
        component: page(() => import('../pages/admin/AdminStudents.vue')),
    },
    {
        path: '/admin/acesso',
        title: 'Acesso',
        component: page(() => import('../pages/admin/AdminAccess.vue')),
    },
    {
        path: '/admin/campanhas',
        title: 'Campanhas',
        component: page(() => import('../pages/admin/AdminCampaigns.vue')),
    },
    {
        path: '/admin/relatorios',
        title: 'Relatórios',
        component: page(() => import('../pages/admin/AdminReports.vue')),
    },
];

function readHash(): string {
    const hash = window.location.hash.replace(/^#/, '');

    return hash === '' ? '/' : hash;
}

export const currentPath = ref(readHash());

window.addEventListener('hashchange', () => {
    currentPath.value = readHash();
    window.scrollTo({ top: 0 });
});

export const currentRoute = computed(
    () =>
        routes.find((route) => route.path === currentPath.value) ?? routes[0]!,
);

export function navigate(path: string): void {
    window.location.hash = path;
}

export function isActive(path: string, exact = false): boolean {
    return exact
        ? currentPath.value === path
        : currentPath.value === path ||
              currentPath.value.startsWith(`${path}/`);
}
