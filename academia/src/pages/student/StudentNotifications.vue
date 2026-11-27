<script setup lang="ts">
import { BellRing, CalendarDays, Info, Sparkles, Wallet } from '@lucide/vue';
import { computed, ref } from 'vue';
import StudentLayout from '../../layouts/StudentLayout.vue';
import { formatShortDate, formatTime } from '../../lib/format';
import { markAllNotificationsRead, state, unreadCount } from '../../lib/store';
import { showToast } from '../../lib/toast';
import type {
    NotificationKind,
    NotificationPreferences,
} from '../../lib/types';

const icons: Record<NotificationKind, typeof Wallet> = {
    mensalidade: Wallet,
    promocao: Sparkles,
    aula: CalendarDays,
    sistema: Info,
};

const filter = ref<'todos' | NotificationKind>('todos');
const filtered = computed(() =>
    state.notifications.filter(
        (notification) =>
            filter.value === 'todos' || notification.kind === filter.value,
    ),
);

const preferenceLabels: {
    key: keyof NotificationPreferences;
    label: string;
    hint: string;
}[] = [
    {
        key: 'push',
        label: 'Notificações no celular',
        hint: 'Mensalidade, aulas e fila de espera',
    },
    {
        key: 'whatsapp',
        label: 'WhatsApp',
        hint: 'Cobranças e lembretes importantes',
    },
    { key: 'email', label: 'E-mail', hint: 'Recibos e comprovantes' },
    {
        key: 'promotions',
        label: 'Promoções e novidades',
        hint: 'Você pode sair quando quiser',
    },
    {
        key: 'quietHours',
        label: 'Silêncio das 22h às 7h',
        hint: 'Nada de notificação à noite',
    },
];

const pushPermission = ref(
    typeof Notification === 'undefined'
        ? 'unsupported'
        : Notification.permission,
);

async function enablePush(): Promise<void> {
    if (typeof Notification === 'undefined') {
        showToast({
            title: 'Instale o app primeiro',
            description:
                'No iPhone, toque em Compartilhar → Adicionar à Tela de Início.',
            tone: 'warn',
        });

        return;
    }

    pushPermission.value = await Notification.requestPermission();
}
</script>

<template>
    <StudentLayout title="Avisos">
        <div class="space-y-2">
            <div class="-mx-5 flex gap-1.5 overflow-x-auto px-5">
                <button
                    v-for="option in [
                        { value: 'todos', label: 'Todos' },
                        { value: 'mensalidade', label: 'Mensalidade' },
                        { value: 'aula', label: 'Aulas' },
                        { value: 'promocao', label: 'Promoções' },
                    ] as const"
                    :key="option.value"
                    type="button"
                    class="min-h-9 shrink-0 rounded-full px-3 text-sm"
                    :class="
                        filter === option.value
                            ? 'bg-ink-100 text-ink-950'
                            : 'bg-ink-900 text-ink-300'
                    "
                    :aria-pressed="filter === option.value"
                    @click="filter = option.value"
                >
                    {{ option.label }}
                </button>
            </div>
            <button
                v-if="unreadCount > 0"
                type="button"
                class="ml-auto block min-h-9 text-sm text-lime-400"
                @click="markAllNotificationsRead"
            >
                Marcar lidas
            </button>
        </div>

        <ul class="space-y-2">
            <li
                v-for="notification in filtered"
                :key="notification.id"
                class="flex gap-3 rounded-2xl p-4"
                :class="
                    notification.read
                        ? 'bg-ink-900/60'
                        : 'bg-ink-900 ring-ink-700 ring-1'
                "
            >
                <span
                    class="grid size-10 shrink-0 place-items-center rounded-xl"
                    :class="
                        notification.kind === 'mensalidade'
                            ? 'bg-warn/15 text-warn'
                            : 'bg-ink-800 text-lime-400'
                    "
                >
                    <component :is="icons[notification.kind]" class="size-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <div class="flex items-start justify-between gap-2">
                        <p class="font-semibold">{{ notification.title }}</p>
                        <span
                            v-if="!notification.read"
                            class="mt-1.5 size-2 shrink-0 rounded-full bg-lime-400"
                            aria-label="Não lida"
                        />
                    </div>
                    <p class="text-ink-300 mt-0.5 text-sm">
                        {{ notification.body }}
                    </p>
                    <div class="mt-2 flex items-center justify-between">
                        <span class="text-ink-400 text-xs"
                            >{{ formatShortDate(notification.createdAt) }} ·
                            {{ formatTime(notification.createdAt) }}</span
                        >
                        <a
                            v-if="notification.action"
                            :href="`#${notification.action.to}`"
                            class="text-ink-950 rounded-lg bg-lime-400 px-3 py-1.5 text-xs font-semibold"
                            @click="notification.read = true"
                        >
                            {{ notification.action.label }}
                        </a>
                    </div>
                </div>
            </li>
            <li
                v-if="filtered.length === 0"
                class="border-ink-700 text-ink-400 rounded-2xl border border-dashed p-6 text-center"
            >
                Nenhum aviso por aqui.
            </li>
        </ul>

        <section
            class="bg-ink-900 rounded-3xl p-5"
            aria-labelledby="prefs-title"
        >
            <h2 id="prefs-title" class="font-display font-bold">
                Preferências
            </h2>
            <p class="text-ink-400 mt-1 text-sm">
                Você escolhe como quer ser avisado (LGPD).
            </p>

            <button
                v-if="pushPermission !== 'granted'"
                type="button"
                class="text-ink-950 mt-4 flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-lime-400 font-semibold"
                @click="enablePush"
            >
                <BellRing class="size-5" /> Ativar notificações neste aparelho
            </button>

            <ul class="divide-ink-800 mt-4 divide-y">
                <li
                    v-for="preference in preferenceLabels"
                    :key="preference.key"
                >
                    <label
                        class="flex cursor-pointer items-center justify-between gap-4 py-3"
                    >
                        <span>
                            <span class="block text-sm font-medium">{{
                                preference.label
                            }}</span>
                            <span class="text-ink-400 block text-xs">{{
                                preference.hint
                            }}</span>
                        </span>
                        <input
                            v-model="state.preferences[preference.key]"
                            type="checkbox"
                            class="peer sr-only"
                        />
                        <span
                            class="bg-ink-700 relative h-7 w-12 shrink-0 rounded-full transition-colors peer-checked:bg-lime-400 peer-focus-visible:outline-2 peer-focus-visible:outline-lime-400 after:absolute after:top-1 after:left-1 after:size-5 after:rounded-full after:bg-white after:transition-transform peer-checked:after:translate-x-5"
                            aria-hidden="true"
                        />
                    </label>
                </li>
            </ul>
        </section>
    </StudentLayout>
</template>
