<script setup lang="ts">
defineProps<{ tone?: 'dark' | 'light' }>();
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-semibold tracking-wide uppercase"
        :class="
            tone === 'light'
                ? 'border-warn/60 bg-warn/15 text-warn-ink'
                : 'border-warn/40 bg-warn/10 text-warn'
        "
        title="Dados de demonstração — nenhum valor exibido é real."
    >
        <span class="size-1.5 rounded-full bg-current" aria-hidden="true" />
        Demonstração
    </span>
</template>
