import { computed, ref } from 'vue';

/**
 * Roteador por hash, sem dependência nova. Rotas:
 *   #/                    → página inicial de demonstração (links)
 *   #/totem/:code         → tela do totem (quiosque, sem toque)
 *   #/t/:code?s=token     → entrada pelo QR (abre o pedido e redireciona)
 *   #/pedido/:id          → fluxo do cliente no celular
 */
export type ResolvedRoute =
    | { name: 'home' }
    | { name: 'kiosk'; totemCode: string }
    | { name: 'entry'; totemCode: string; token: string }
    | { name: 'order'; orderId: string }
    | { name: 'not-found' };

function currentPath(): string {
    const path = window.location.hash.replace(/^#/, '');

    return path === '' ? '/' : path;
}

const path = ref(currentPath());

window.addEventListener('hashchange', () => {
    path.value = currentPath();
    window.scrollTo({ top: 0 });
});

export function resolve(target: string): ResolvedRoute {
    const [pathname, query = ''] = target.split('?');
    const params = new URLSearchParams(query);

    if (pathname === '/') {
        return { name: 'home' };
    }

    const kiosk = /^\/totem\/([\w-]+)$/.exec(pathname);

    if (kiosk) {
        return { name: 'kiosk', totemCode: kiosk[1] };
    }

    const entry = /^\/t\/([\w-]+)$/.exec(pathname);

    if (entry) {
        return {
            name: 'entry',
            totemCode: entry[1],
            token: params.get('s') ?? '',
        };
    }

    const order = /^\/pedido\/([\w-]+)$/.exec(pathname);

    if (order) {
        return { name: 'order', orderId: order[1] };
    }

    return { name: 'not-found' };
}

export const route = computed(() => resolve(path.value));

export function navigate(target: string, replace = false): void {
    if (replace) {
        window.location.replace(`#${target}`);
    } else {
        window.location.hash = target;
    }
}
