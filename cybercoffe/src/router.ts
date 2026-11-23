import { computed, ref } from 'vue';

/**
 * Roteador por hash, sem dependência nova. Rotas:
 *   #/checkin[/:id]     → seleção de estação (opcionalmente pré-selecionada) e plano
 *   #/maquina/:id       → tela da máquina (timer)
 *   #/maquina           → escolha da máquina
 *   #/admin             → painel administrativo
 */
export type RouteName = 'checkin' | 'machine' | 'admin' | 'not-found';

export interface ResolvedRoute {
    name: RouteName;
    stationId: number | null;
}

function currentPath(): string {
    const path = window.location.hash.replace(/^#/, '');

    return path === '' || path === '/' ? '/checkin' : path;
}

const path = ref(currentPath());

window.addEventListener('hashchange', () => {
    path.value = currentPath();
    window.scrollTo({ top: 0 });
});

export function resolve(target: string): ResolvedRoute {
    const checkin = /^\/checkin(?:\/(\d+))?$/.exec(target);

    if (checkin) {
        return {
            name: 'checkin',
            stationId: checkin[1] ? Number(checkin[1]) : null,
        };
    }

    if (target === '/admin') {
        return { name: 'admin', stationId: null };
    }

    const machine = /^\/maquina(?:\/(\d+))?$/.exec(target);

    if (machine) {
        return {
            name: 'machine',
            stationId: machine[1] ? Number(machine[1]) : null,
        };
    }

    return { name: 'not-found', stationId: null };
}

export const route = computed(() => resolve(path.value));

export function navigate(target: string): void {
    window.location.hash = target;
}

export function machinePath(stationId: number): string {
    return `/maquina/${stationId}`;
}
