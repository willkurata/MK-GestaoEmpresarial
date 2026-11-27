import { entryFromLocation } from '@totem/lib/entry';
import { computed, ref } from 'vue';

/**
 * Roteador por hash, sem dependência nova. Rotas:
 *   #/                    → página inicial de demonstração (links)
 *   #/totem/:code         → tela do totem (quiosque, sem toque)
 *   #/t/:code?t=token     → entrada pelo QR (abre o pedido e redireciona)
 *   #/pedido/:id          → fluxo do cliente no celular
 *
 * O backend (WIL-46) monta o QR como `{TOTEM_CUSTOMER_URL}/{code}?t=token`.
 * Com `TOTEM_CUSTOMER_URL=https://host/#/t` o link já cai na rota de entrada;
 * na forma por caminho (`https://host/totem/A1?t=…`, padrão do backend) o
 * link é convertido em `#/t/A1?t=…` ao abrir a página.
 */
export type ResolvedRoute =
    | { name: 'home' }
    | { name: 'kiosk'; totemCode: string }
    | { name: 'entry'; totemCode: string; token: string }
    | { name: 'order'; orderId: string }
    | { name: 'not-found' };

if (window.location.hash === '') {
    const entry = entryFromLocation(
        window.location.pathname,
        window.location.search,
    );

    if (entry) {
        // Limpa o token da barra de endereço e entra pela rota de hash.
        window.history.replaceState(
            null,
            '',
            `${window.location.pathname}#${entry}`,
        );
    }
}

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
            // `t` é o nome do backend; `s` fica por compatibilidade.
            token: params.get('t') ?? params.get('s') ?? '',
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
