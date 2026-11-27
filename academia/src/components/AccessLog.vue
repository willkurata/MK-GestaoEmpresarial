<script setup lang="ts">
import { CircleCheck, CircleX, LogOut } from '@lucide/vue';
import { denialLabel } from '../lib/access';
import { formatTime } from '../lib/format';
import type { AccessEvent, IdentificationMethod } from '../lib/types';

defineProps<{ events: AccessEvent[] }>();

const methodLabels: Record<IdentificationMethod, string> = {
    facial: 'Facial',
    qr: 'QR',
    rfid: 'Cartão',
    totem: 'Totem',
};
</script>

<template>
    <ul class="divide-y divide-slate-100" aria-live="polite">
        <li
            v-for="event in events"
            :key="event.id"
            class="animate-fade-up flex items-center gap-3 py-2.5"
        >
            <span
                class="grid size-8 shrink-0 place-items-center rounded-full"
                :class="
                    !event.allowed
                        ? 'text-danger-ink bg-red-50'
                        : event.direction === 'saida'
                          ? 'bg-slate-100 text-slate-500'
                          : 'bg-emerald-50 text-emerald-700'
                "
            >
                <CircleX v-if="!event.allowed" class="size-4" />
                <LogOut
                    v-else-if="event.direction === 'saida'"
                    class="size-4"
                />
                <CircleCheck v-else class="size-4" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium">
                    {{ event.studentName }}
                </p>
                <p class="truncate text-xs text-slate-500">
                    <template v-if="event.allowed">{{
                        event.direction === 'entrada' ? 'Entrou' : 'Saiu'
                    }}</template>
                    <template v-else
                        >Bloqueado ·
                        {{
                            event.reason ? denialLabel(event.reason) : ''
                        }}</template
                    >
                    · {{ methodLabels[event.method] }}
                </p>
            </div>
            <time
                class="tabular shrink-0 text-xs text-slate-400"
                :datetime="event.at"
                >{{ formatTime(event.at) }}</time
            >
        </li>
        <li
            v-if="events.length === 0"
            class="py-6 text-center text-sm text-slate-500"
        >
            Nenhum acesso registrado ainda.
        </li>
    </ul>
</template>
