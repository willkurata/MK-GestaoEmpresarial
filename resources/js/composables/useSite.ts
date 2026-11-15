import { usePage } from '@inertiajs/vue3';
import { computed, onUnmounted, ref, watchEffect } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import type { SiteSharedProps } from '@/types/site';

export function useSite(): ComputedRef<SiteSharedProps> {
    const page = usePage();

    return computed(() => page.props.site as SiteSharedProps);
}

const whatsappContextMessage = ref<string | null>(null);

/**
 * Mensagem contextual do botão flutuante de WhatsApp. Uma página (ex.:
 * detalhe de imóvel) define a mensagem; ao sair dela, volta ao padrão.
 */
export function useWhatsappContext(message?: () => string): Ref<string | null> {
    if (message) {
        watchEffect(() => {
            whatsappContextMessage.value = message();
        });
        onUnmounted(() => {
            whatsappContextMessage.value = null;
        });
    }

    return whatsappContextMessage;
}
