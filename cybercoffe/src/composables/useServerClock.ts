import type { Session, StationStatus } from '@cc/types';
import { onBeforeUnmount, onMounted, ref } from 'vue';

/** Minutos restantes a partir dos quais a máquina aparece como "Acabando". */
export const ENDING_THRESHOLD_SECONDS = 5 * 60;

/**
 * Diferença entre o relógio do servidor e o local. O servidor é a fonte
 * da verdade do tempo (WIL-34/35); o PC do cliente apenas desenha.
 */
const serverOffsetMs = ref(0);

export function syncServerClock(serverTime: string): void {
    const parsed = new Date(serverTime).getTime();

    if (!Number.isNaN(parsed)) {
        serverOffsetMs.value = parsed - Date.now();
    }
}

/** Relógio reativo alinhado ao servidor, atualizado a cada segundo. */
export function useServerNow() {
    const now = ref(Date.now() + serverOffsetMs.value);
    let timer: number | undefined;

    onMounted(() => {
        timer = window.setInterval(() => {
            now.value = Date.now() + serverOffsetMs.value;
        }, 1000);
    });

    onBeforeUnmount(() => window.clearInterval(timer));

    return now;
}

/** Tempo restante do pacote. Tempo livre não tem fim previsto: retorna 0. */
export function remainingSeconds(session: Session, nowMs: number): number {
    if (session.status === 'ended' || session.billing === 'open') {
        return 0;
    }

    if (session.status === 'paused') {
        return session.paused_remaining_seconds ?? 0;
    }

    return Math.max(
        0,
        Math.round(
            (new Date(session.ends_at ?? nowMs).getTime() - nowMs) / 1000,
        ),
    );
}

export function displayStatus(
    status: StationStatus,
    session: Session | null,
    nowMs: number,
): StationStatus | 'ending' {
    if (
        status === 'in_use' &&
        session &&
        session.billing === 'package' &&
        remainingSeconds(session, nowMs) <= ENDING_THRESHOLD_SECONDS
    ) {
        return 'ending';
    }

    return status;
}
