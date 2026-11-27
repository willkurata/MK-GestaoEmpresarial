import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Volta o totem ao início depois de `seconds` sem toque. Expõe os segundos
 * restantes para mostrar o aviso "voltando ao início em…".
 */
export function useIdleReset(seconds: number, onIdle: () => void) {
    const secondsLeft = ref(seconds);
    let timer = 0;

    const reset = (): void => {
        secondsLeft.value = seconds;
    };

    const events = ['pointerdown', 'keydown', 'touchstart'] as const;

    onMounted(() => {
        events.forEach((name) =>
            window.addEventListener(name, reset, { passive: true }),
        );
        timer = window.setInterval(() => {
            secondsLeft.value--;

            if (secondsLeft.value <= 0) {
                window.clearInterval(timer);
                onIdle();
            }
        }, 1000);
    });

    onBeforeUnmount(() => {
        events.forEach((name) => window.removeEventListener(name, reset));
        window.clearInterval(timer);
    });

    return { secondsLeft, reset };
}
