import { onBeforeUnmount, ref } from 'vue';

/** Relógio reativo — atualiza a cada `intervalMs`. */
export function useNow(intervalMs = 1000) {
    const now = ref(new Date());
    const timer = window.setInterval(() => {
        now.value = new Date();
    }, intervalMs);

    onBeforeUnmount(() => window.clearInterval(timer));

    return now;
}
