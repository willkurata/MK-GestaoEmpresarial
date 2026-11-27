import { reactive } from 'vue';

export type Toast = {
    id: number;
    title: string;
    description?: string;
    tone: 'ok' | 'warn' | 'info';
};

export const toasts = reactive<Toast[]>([]);
let nextId = 1;

export function showToast(toast: Omit<Toast, 'id'>, durationMs = 4000): void {
    const id = nextId++;
    toasts.push({ ...toast, id });

    window.setTimeout(() => {
        const index = toasts.findIndex((item) => item.id === id);

        if (index >= 0) {
            toasts.splice(index, 1);
        }
    }, durationMs);
}
