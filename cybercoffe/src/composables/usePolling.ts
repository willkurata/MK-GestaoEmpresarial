import { onBeforeUnmount, onMounted } from 'vue';

/**
 * Atualiza dados periodicamente enquanto a aba está visível.
 * Substituto simples até o tempo real via Laravel Reverb (WIL-34, V2).
 */
export function usePolling(task: () => Promise<void>, intervalMs: number) {
    let timer: number | undefined;

    async function tick(): Promise<void> {
        if (document.visibilityState === 'visible') {
            await task();
        }
    }

    onMounted(() => {
        void task();
        timer = window.setInterval(() => void tick(), intervalMs);
    });

    onBeforeUnmount(() => window.clearInterval(timer));
}
