<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import type { OpenCashSession } from '@/types';

const props = withDefaults(
    defineProps<{
        sessions: OpenCashSession[];
        /** Mostra o nome da empresa em cada linha (painel MK com várias empresas). */
        showCompany?: boolean;
        /** Destino opcional de cada linha, ex.: página da empresa no painel MK. */
        companyHref?: (
            session: OpenCashSession,
        ) => NonNullable<InertiaLinkProps['href']>;
        /** Ação sugerida exibida abaixo da lista. */
        hint?: string;
    }>(),
    { showCompany: false, companyHref: undefined, hint: undefined },
);

const staleCount = computed(
    () => props.sessions.filter((session) => session.isStale).length,
);
const title = computed(() => {
    if (props.sessions.length === 1) {
        return props.sessions[0].isStale
            ? 'Caixa esquecido em aberto'
            : 'Caixa em aberto';
    }

    return `${props.sessions.length} caixas em aberto`;
});

function durationLabel(session: OpenCashSession): string {
    if (session.hoursOpen < 1) {
        return 'há menos de 1 hora';
    }

    if (session.hoursOpen < 48) {
        return `há ${session.hoursOpen} h`;
    }

    return `há ${Math.floor(session.hoursOpen / 24)} dias`;
}
</script>

<template>
    <section
        v-if="sessions.length > 0"
        role="alert"
        aria-live="polite"
        data-test="open-cash-session-alert"
        :class="[
            'rounded-xl border p-4 sm:p-5',
            staleCount > 0
                ? 'border-orange-500/50 bg-orange-50 text-orange-950 dark:border-orange-400/40 dark:bg-orange-500/10 dark:text-orange-50'
                : 'border-amber-500/50 bg-amber-50 text-amber-950 dark:border-amber-400/40 dark:bg-amber-500/10 dark:text-amber-50',
        ]"
    >
        <div class="flex items-start gap-3">
            <TriangleAlert
                :class="[
                    'mt-0.5 size-5 shrink-0',
                    staleCount > 0
                        ? 'text-orange-600 dark:text-orange-300'
                        : 'text-amber-600 dark:text-amber-300',
                ]"
                aria-hidden="true"
            />
            <div class="min-w-0 flex-1">
                <h2 class="font-semibold">{{ title }}</h2>
                <p class="mt-1 text-sm opacity-90">
                    <template v-if="staleCount > 0">
                        {{
                            staleCount === 1
                                ? 'Um turno foi aberto'
                                : `${staleCount} turnos foram abertos`
                        }}
                        em dia anterior e não
                        {{
                            staleCount === 1 ? 'foi fechado' : 'foram fechados'
                        }}.
                    </template>
                    Um novo caixa só pode ser aberto depois que o turno atual
                    for fechado por quem o abriu.
                </p>

                <ul class="mt-3 flex flex-col gap-2 text-sm">
                    <li
                        v-for="session in sessions"
                        :key="session.id"
                        class="flex flex-col gap-1 rounded-lg border border-current/15 bg-white/60 px-3 py-2 sm:flex-row sm:items-center sm:justify-between dark:bg-black/20"
                    >
                        <span class="min-w-0">
                            <template v-if="showCompany">
                                <Link
                                    v-if="companyHref"
                                    :href="companyHref(session)"
                                    class="font-semibold underline-offset-2 hover:underline"
                                    >{{ session.company.name }}</Link
                                >
                                <strong v-else>{{
                                    session.company.name
                                }}</strong>
                                <span class="opacity-70"> · </span>
                            </template>
                            <span
                                >{{ session.register }} aberto por
                                <strong>{{ session.operator }}</strong></span
                            >
                        </span>
                        <span class="shrink-0 opacity-80">
                            desde {{ session.openedAt }} ({{
                                durationLabel(session)
                            }})
                            <span
                                v-if="session.isStale"
                                class="ml-1 rounded-full bg-orange-600 px-2 py-0.5 text-xs font-semibold text-white"
                                >Dia anterior</span
                            >
                        </span>
                    </li>
                </ul>

                <p v-if="hint" class="mt-3 text-sm opacity-90">{{ hint }}</p>
            </div>
        </div>
    </section>
</template>
