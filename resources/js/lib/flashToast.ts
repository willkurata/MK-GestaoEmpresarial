import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import type { FlashToast } from '@/types/ui';

/**
 * Mensagens amigáveis para respostas HTTP de erro que chegam fora do
 * fluxo de validação (por exemplo, `abort_if(..., 422, ...)` no servidor).
 * Sem este tratamento o Inertia abre um modal com a página de erro bruta.
 */
const httpErrorMessages: Record<number, string> = {
    403: 'Seu perfil não tem permissão para esta ação.',
    404: 'O registro não foi encontrado. Atualize a página e tente novamente.',
    419: 'Sua sessão expirou. Atualizando a página…',
    422: 'Não foi possível concluir a operação. Confira o estado do caixa e tente novamente.',
    429: 'Muitas tentativas seguidas. Aguarde um instante e tente novamente.',
};

export function initializeFlashToast(): void {
    router.on('flash', (event) => {
        const flash = (event as CustomEvent).detail?.flash;
        const data = flash?.toast as FlashToast | undefined;

        if (!data) {
            return;
        }

        toast[data.type](data.message);
    });

    // Mensagens de sucesso enviadas pelo servidor via `->with('success', ...)`.
    // As páginas do painel MK já exibem essas mensagens no próprio conteúdo.
    router.on('success', (event) => {
        const page = event.detail.page;
        const message = (
            page.props.flash as { success?: string | null } | undefined
        )?.success;

        if (message && !page.component.startsWith('mk/')) {
            toast.success(message);
        }
    });

    router.on('httpException', (event) => {
        const status = event.detail.response.status;
        const message = httpErrorMessages[status];

        if (!message) {
            return;
        }

        event.preventDefault();
        toast.error(message);

        if (status === 419) {
            router.reload();
        }
    });
}
