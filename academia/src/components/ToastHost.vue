<script setup lang="ts">
import { CircleCheck, Info, TriangleAlert } from '@lucide/vue';
import { toasts } from '../lib/toast';

const icons = { ok: CircleCheck, warn: TriangleAlert, info: Info };
</script>

<template>
    <div
        class="pointer-events-none fixed inset-x-0 top-3 z-50 flex flex-col items-center gap-2 px-4"
        aria-live="polite"
    >
        <div
            v-for="toast in toasts"
            :key="toast.id"
            class="animate-fade-up border-ink-700 bg-ink-900/95 text-ink-100 pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-2xl border p-3.5 shadow-xl backdrop-blur"
            role="status"
        >
            <component
                :is="icons[toast.tone]"
                class="mt-0.5 size-5 shrink-0"
                :class="{
                    'text-lime-400': toast.tone === 'ok',
                    'text-warn': toast.tone === 'warn',
                    'text-ink-300': toast.tone === 'info',
                }"
            />
            <div class="min-w-0">
                <p class="text-sm font-semibold">{{ toast.title }}</p>
                <p v-if="toast.description" class="text-ink-300 mt-0.5 text-sm">
                    {{ toast.description }}
                </p>
            </div>
        </div>
    </div>
</template>
