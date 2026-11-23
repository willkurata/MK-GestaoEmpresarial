<script setup lang="ts">
import { CircleCheck, TriangleAlert, X } from '@lucide/vue';

defineProps<{
    tone: 'success' | 'error';
    message: string;
}>();

defineEmits<{ dismiss: [] }>();
</script>

<template>
    <div
        :role="tone === 'error' ? 'alert' : 'status'"
        class="pixel-corners animate-rise flex items-start gap-3 px-4 py-3 text-sm"
        :class="
            tone === 'error'
                ? 'bg-danger/12 text-danger ring-danger/40 ring-1 ring-inset'
                : 'bg-neon/10 text-neon ring-neon/40 ring-1 ring-inset'
        "
    >
        <TriangleAlert v-if="tone === 'error'" class="mt-0.5 size-4 shrink-0" />
        <CircleCheck v-else class="mt-0.5 size-4 shrink-0" />
        <p class="text-ink flex-1">{{ message }}</p>
        <button
            type="button"
            class="text-dim hover:text-ink -m-1 p-1"
            aria-label="Fechar aviso"
            @click="$emit('dismiss')"
        >
            <X class="size-4" />
        </button>
    </div>
</template>
