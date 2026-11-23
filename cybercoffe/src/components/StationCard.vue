<script setup lang="ts">
import StatusBadge from '@cc/components/StatusBadge.vue';
import { TIER_LABEL } from '@cc/lib/format';
import type { Station } from '@cc/types';
import { Cpu, Gamepad2, Monitor } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{
    station: Station;
    selected: boolean;
}>();

defineEmits<{ select: [stationId: number] }>();

const isAvailable = computed(() => props.station.status === 'free');
const icon = computed(() =>
    props.station.tier === 'console'
        ? Gamepad2
        : props.station.tier === 'gamer'
          ? Cpu
          : Monitor,
);
</script>

<template>
    <button
        type="button"
        role="radio"
        :aria-checked="selected"
        :disabled="!isAvailable"
        class="pixel-corners group relative flex min-h-32 w-full flex-col justify-between gap-3 p-4 text-left transition-[background-color,box-shadow] duration-150 disabled:cursor-not-allowed"
        :class="
            selected
                ? 'bg-neon/10 shadow-[inset_0_0_0_2px_var(--color-neon)]'
                : isAvailable
                  ? 'bg-panel-2 hover:bg-line shadow-[inset_0_0_0_1px_var(--color-line-strong)]'
                  : 'bg-panel opacity-55 shadow-[inset_0_0_0_1px_var(--color-line)]'
        "
        @click="$emit('select', station.id)"
    >
        <span class="flex items-start justify-between gap-2">
            <component
                :is="icon"
                class="size-5"
                :class="
                    selected ? 'text-neon' : 'text-dim group-hover:text-ink'
                "
            />
            <StatusBadge :status="station.status" />
        </span>
        <span>
            <span class="block text-xl font-bold tracking-wide">
                {{ station.name }}
            </span>
            <span class="text-dim mt-0.5 block text-xs">
                {{ TIER_LABEL[station.tier] }} · {{ station.specs }}
            </span>
        </span>
    </button>
</template>
