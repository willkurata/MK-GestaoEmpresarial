import { onBeforeUnmount, ref } from 'vue';

type NotifyPermission = NotificationPermission | 'unsupported';

/**
 * Avisos de "impressão concluída" que funcionam em qualquer celular, sem
 * depender de Web Push (no iPhone ele só existe com o site na Tela de
 * Início, iOS 16.4+):
 *  - vibração (Android; o iOS ignora);
 *  - som curto gerado na hora (Web Audio, liberado por um toque antes);
 *  - notificação do sistema, se a pessoa permitir e a aba estiver em 2º plano;
 *  - título da aba piscando;
 *  - tela acesa (Wake Lock) enquanto espera.
 */
export function useCompletionAlert() {
    const permission = ref<NotifyPermission>(
        'Notification' in window ? Notification.permission : 'unsupported',
    );
    const soundReady = ref(false);
    const keepingAwake = ref(false);

    let audio: AudioContext | null = null;
    let wakeLock: WakeLockSentinel | null = null;
    let titleTimer: number | undefined;
    const originalTitle = document.title;

    /** Chame num toque do usuário: libera o som e pede permissão. */
    async function enable(): Promise<void> {
        try {
            audio ??= new AudioContext();
            await audio.resume();
            soundReady.value = audio.state === 'running';
        } catch {
            soundReady.value = false;
        }

        if ('Notification' in window && Notification.permission === 'default') {
            try {
                permission.value = await Notification.requestPermission();
            } catch {
                permission.value = Notification.permission;
            }
        }
    }

    async function keepAwake(): Promise<void> {
        if (!('wakeLock' in navigator) || wakeLock) {
            return;
        }

        try {
            wakeLock = await navigator.wakeLock.request('screen');
            keepingAwake.value = true;
            wakeLock.addEventListener('release', () => {
                wakeLock = null;
                keepingAwake.value = false;
            });
        } catch {
            keepingAwake.value = false;
        }
    }

    function releaseAwake(): void {
        void wakeLock?.release().catch(() => undefined);
        wakeLock = null;
        keepingAwake.value = false;
    }

    function chime(notes: number[]): void {
        if (!audio || audio.state !== 'running') {
            return;
        }

        notes.forEach((frequency, index) => {
            const start = audio!.currentTime + index * 0.16;
            const oscillator = audio!.createOscillator();
            const gain = audio!.createGain();
            oscillator.type = 'sine';
            oscillator.frequency.value = frequency;
            gain.gain.setValueAtTime(0.0001, start);
            gain.gain.exponentialRampToValueAtTime(0.25, start + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.45);
            oscillator.connect(gain).connect(audio!.destination);
            oscillator.start(start);
            oscillator.stop(start + 0.5);
        });
    }

    function flashTitle(message: string): void {
        window.clearInterval(titleTimer);
        let on = false;
        titleTimer = window.setInterval(() => {
            on = !on;
            document.title = on ? message : originalTitle;

            if (document.visibilityState === 'visible') {
                window.clearInterval(titleTimer);
                document.title = originalTitle;
            }
        }, 1000);
    }

    function systemNotification(title: string, body: string): void {
        if (
            permission.value !== 'granted' ||
            document.visibilityState === 'visible'
        ) {
            return;
        }

        try {
            new Notification(title, { body, tag: 'totem-print' });
        } catch {
            // Android Chrome exige service worker para `new Notification`.
        }
    }

    function notifyReady(pickupCode: string): void {
        navigator.vibrate?.([200, 100, 200, 100, 400]);
        chime([660, 880, 1320]);
        flashTitle(`✅ Pronto! Código ${pickupCode}`);
        systemNotification(
            'Sua impressão está pronta',
            `Retire no totem com o código ${pickupCode}.`,
        );
        releaseAwake();
    }

    function notifyProblem(message: string): void {
        navigator.vibrate?.([500, 200, 500]);
        chime([440, 330]);
        flashTitle('⚠️ Atenção ao seu pedido');
        systemNotification('Problema na impressão', message);
        releaseAwake();
    }

    onBeforeUnmount(() => {
        window.clearInterval(titleTimer);
        document.title = originalTitle;
        releaseAwake();
        void audio?.close().catch(() => undefined);
    });

    return {
        permission,
        soundReady,
        keepingAwake,
        enable,
        keepAwake,
        notifyReady,
        notifyProblem,
    };
}
