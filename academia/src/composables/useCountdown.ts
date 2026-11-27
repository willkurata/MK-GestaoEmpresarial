import { computed, onBeforeUnmount, ref } from 'vue';

/**
 * Contagem regressiva com precisão de animação (requestAnimationFrame),
 * usada pelo anel de tempo da catraca. `onFinish` dispara uma única vez.
 */
export function useCountdown(onFinish: () => void) {
    const durationMs = ref(0);
    const remainingMs = ref(0);
    const running = ref(false);
    let endAt = 0;
    let frame = 0;

    const tick = (): void => {
        remainingMs.value = Math.max(0, endAt - performance.now());

        if (remainingMs.value <= 0) {
            running.value = false;
            onFinish();

            return;
        }

        frame = requestAnimationFrame(tick);
    };

    function start(seconds: number): void {
        cancelAnimationFrame(frame);
        durationMs.value = seconds * 1000;
        remainingMs.value = durationMs.value;
        endAt = performance.now() + durationMs.value;
        running.value = true;
        frame = requestAnimationFrame(tick);
    }

    function stop(): void {
        cancelAnimationFrame(frame);
        running.value = false;
        remainingMs.value = 0;
    }

    onBeforeUnmount(() => cancelAnimationFrame(frame));

    const progress = computed(() =>
        durationMs.value === 0 ? 0 : remainingMs.value / durationMs.value,
    );
    const secondsLeft = computed(() => Math.ceil(remainingMs.value / 1000));

    return { start, stop, running, progress, secondsLeft };
}
