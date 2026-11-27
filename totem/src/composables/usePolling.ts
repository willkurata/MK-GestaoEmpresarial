import { onBeforeUnmount, onMounted } from 'vue';

/**
 * Repete `task` enquanto a aba está visível e volta a buscar assim que ela
 * reaparece. Substituto do tempo real (Laravel Reverb) até ele existir.
 */
export function usePolling(task: () => Promise<void>, intervalMs: number) {
    let timer: number | undefined;
    let running = false;

    async function tick(): Promise<void> {
        if (running || document.visibilityState !== 'visible') {
            return;
        }

        running = true;

        try {
            await task();
        } finally {
            running = false;
        }
    }

    function onVisibility(): void {
        if (document.visibilityState === 'visible') {
            void tick();
        }
    }

    onMounted(() => {
        void tick();
        timer = window.setInterval(() => void tick(), intervalMs);
        document.addEventListener('visibilitychange', onVisibility);
    });

    onBeforeUnmount(() => {
        window.clearInterval(timer);
        document.removeEventListener('visibilitychange', onVisibility);
    });
}
