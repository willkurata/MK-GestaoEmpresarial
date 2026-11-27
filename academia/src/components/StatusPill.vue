<script setup lang="ts">
import { Ban, CircleCheck, Moon, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import type { StudentStatus } from '../lib/types';

const props = defineProps<{ status: StudentStatus; tone?: 'dark' | 'light' }>();

const config = computed(() => {
    switch (props.status) {
        case 'ativo':
            return {
                label: 'Em dia',
                icon: CircleCheck,
                classes:
                    props.tone === 'light'
                        ? 'bg-emerald-50 text-emerald-800 ring-emerald-200'
                        : 'bg-lime-400/10 text-lime-400 ring-lime-400/30',
            };
        case 'inadimplente':
            return {
                label: 'Em atraso',
                icon: TriangleAlert,
                classes:
                    props.tone === 'light'
                        ? 'bg-amber-50 text-warn-ink ring-amber-200'
                        : 'bg-warn/10 text-warn ring-warn/30',
            };
        case 'bloqueado':
            return {
                label: 'Bloqueado',
                icon: Ban,
                classes:
                    props.tone === 'light'
                        ? 'bg-red-50 text-danger-ink ring-red-200'
                        : 'bg-danger/10 text-danger ring-danger/30',
            };
        case 'inativo':
            return {
                label: 'Inativo',
                icon: Moon,
                classes:
                    props.tone === 'light'
                        ? 'bg-slate-100 text-slate-600 ring-slate-200'
                        : 'bg-ink-700 text-ink-300 ring-ink-600',
            };
    }
});
</script>

<template>
    <span
        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
        :class="config.classes"
    >
        <component :is="config.icon" class="size-3.5" aria-hidden="true" />
        {{ config.label }}
    </span>
</template>
