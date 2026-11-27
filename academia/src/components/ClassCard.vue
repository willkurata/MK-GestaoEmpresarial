<script setup lang="ts">
import { Clock, Users } from '@lucide/vue';
import { computed } from 'vue';
import { bookingStateFor, spotsLeft } from '../lib/classes';
import { formatTime } from '../lib/format';
import type { GymClass } from '../lib/types';

const props = defineProps<{
    gymClass: GymClass;
    studentId: string;
    size?: 'app' | 'kiosk';
}>();
const emit = defineEmits<{ book: []; cancel: [] }>();

const booking = computed(() =>
    bookingStateFor(props.gymClass, props.studentId),
);
const spots = computed(() => spotsLeft(props.gymClass));
const fillRatio = computed(
    () => props.gymClass.bookedStudentIds.length / props.gymClass.capacity,
);
const waitPosition = computed(
    () => props.gymClass.waitlistStudentIds.indexOf(props.studentId) + 1,
);
const hasStarted = computed(
    () => new Date(props.gymClass.startsAt).getTime() < Date.now(),
);
</script>

<template>
    <article
        class="bg-ink-900 flex items-center gap-4 rounded-2xl border"
        :class="[
            booking === 'confirmada' ? 'border-lime-400/50' : 'border-ink-800',
            size === 'kiosk' ? 'p-5' : 'p-4',
            hasStarted ? 'opacity-50' : '',
        ]"
    >
        <div class="w-16 shrink-0 text-center">
            <p
                class="font-display tabular font-bold"
                :class="size === 'kiosk' ? 'text-3xl' : 'text-xl'"
            >
                {{ formatTime(gymClass.startsAt) }}
            </p>
            <p
                class="text-ink-400 flex items-center justify-center gap-1 text-xs"
            >
                <Clock class="size-3" />{{ gymClass.durationMinutes }} min
            </p>
        </div>
        <div class="min-w-0 flex-1">
            <p
                class="truncate font-semibold"
                :class="size === 'kiosk' ? 'text-xl' : ''"
            >
                {{ gymClass.name }}
            </p>
            <p class="text-ink-400 truncate text-sm">
                {{ gymClass.coach }} · {{ gymClass.room }}
            </p>
            <div class="text-ink-300 mt-2 flex items-center gap-2 text-xs">
                <Users class="size-3.5" />
                <div class="bg-ink-700 h-1 w-16 rounded-full">
                    <div
                        class="h-full rounded-full"
                        :class="spots === 0 ? 'bg-warn' : 'bg-ink-300'"
                        :style="{ width: `${fillRatio * 100}%` }"
                    />
                </div>
                <span class="tabular">
                    {{
                        spots > 0
                            ? `${spots} vagas`
                            : `Lotada · ${gymClass.waitlistStudentIds.length} na fila`
                    }}
                </span>
            </div>
        </div>

        <span v-if="hasStarted" class="text-ink-400 text-xs">Encerrada</span>
        <button
            v-else-if="booking === 'livre'"
            type="button"
            class="shrink-0 rounded-xl font-semibold active:scale-95"
            :class="[
                spots > 0
                    ? 'text-ink-950 bg-lime-400'
                    : 'border-ink-600 text-ink-100 border',
                size === 'kiosk'
                    ? 'min-h-16 px-6 text-lg'
                    : 'min-h-11 px-4 text-sm',
            ]"
            @click="emit('book')"
        >
            {{ spots > 0 ? 'Reservar' : 'Entrar na fila' }}
        </button>
        <div v-else class="flex shrink-0 flex-col items-end gap-1">
            <span
                class="rounded-full px-2 py-0.5 text-xs font-semibold"
                :class="
                    booking === 'confirmada'
                        ? 'bg-lime-400/15 text-lime-400'
                        : 'bg-warn/15 text-warn'
                "
            >
                {{
                    booking === 'confirmada'
                        ? 'Confirmada'
                        : `Fila · ${waitPosition}º`
                }}
            </span>
            <button
                type="button"
                class="text-ink-400 hover:text-ink-100 min-h-9 px-1 text-xs underline-offset-2 hover:underline"
                @click="emit('cancel')"
            >
                {{
                    booking === 'confirmada'
                        ? 'Cancelar reserva'
                        : 'Sair da fila'
                }}
            </button>
        </div>
    </article>
</template>
