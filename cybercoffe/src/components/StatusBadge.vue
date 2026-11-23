<script setup lang="ts">
import { STATUS_LABEL } from '@cc/lib/format';
import type { StationStatus } from '@cc/types';
import { computed } from 'vue';

/** Estado sempre com texto + cor, nunca só cor. */
const props = defineProps<{ status: StationStatus | 'ending' }>();

const TONE: Record<StationStatus | 'ending', string> = {
    free: 'text-neon bg-neon/10 ring-neon/40',
    in_use: 'text-sky bg-sky/10 ring-sky/40',
    ending: 'text-amber bg-amber/10 ring-amber/50',
    paused: 'text-violet bg-violet/10 ring-violet/40',
    reserved: 'text-violet bg-violet/10 ring-violet/40',
    maintenance: 'text-idle bg-idle/15 ring-idle/40',
    offline: 'text-danger bg-danger/10 ring-danger/40',
};

const tone = computed(() => TONE[props.status]);
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 px-2 py-1 text-[11px] font-semibold tracking-wider uppercase ring-1 ring-inset"
        :class="tone"
    >
        <span
            aria-hidden="true"
            class="size-1.5 bg-current"
            :class="{ 'animate-blink': status === 'ending' }"
        />
        {{ STATUS_LABEL[status] }}
    </span>
</template>
