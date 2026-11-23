import { ref } from 'vue';

export interface Flash {
    tone: 'success' | 'error';
    message: string;
}

/** Aviso único por tela que some sozinho depois de alguns segundos. */
export function useFlash(timeoutMs = 5000) {
    const flash = ref<Flash | null>(null);
    let timer: number | undefined;

    function show(tone: Flash['tone'], message: string): void {
        window.clearTimeout(timer);
        flash.value = { tone, message };
        timer = window.setTimeout(() => (flash.value = null), timeoutMs);
    }

    function dismiss(): void {
        window.clearTimeout(timer);
        flash.value = null;
    }

    return { flash, show, dismiss };
}
