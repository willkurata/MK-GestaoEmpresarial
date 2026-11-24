<script setup lang="ts">
defineProps<{ size?: 'sm' | 'lg' }>();
</script>

<template>
    <div class="flex items-center gap-2.5">
        <span
            aria-hidden="true"
            :class="[
                'bg-process text-on-process relative grid place-items-center rounded-xl',
                size === 'lg' ? 'size-14' : 'size-9',
            ]"
        >
            <svg
                viewBox="0 0 24 24"
                :class="size === 'lg' ? 'size-8' : 'size-5'"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <path d="M7 9V3h10v6" />
                <rect x="3" y="9" width="18" height="8" rx="2" />
                <path d="M7 14h10v7H7z" />
                <path d="M10 17.5h4" />
            </svg>
        </span>
        <span class="leading-none">
            <span
                :class="[
                    'font-display block font-extrabold tracking-tight',
                    size === 'lg' ? 'text-4xl' : 'text-lg',
                ]"
                >TOTEM PRINT</span
            >
            <span
                :class="[
                    'text-process block font-mono font-bold',
                    size === 'lg' ? 'mt-1 text-base' : 'text-[11px]',
                ]"
                >24 HORAS · AUTOATENDIMENTO</span
            >
        </span>
    </div>
</template>
