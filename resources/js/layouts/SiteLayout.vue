<script setup lang="ts">
import SiteFooter from '@/components/site/SiteFooter.vue';
import SiteHeader from '@/components/site/SiteHeader.vue';
import WhatsAppFloat from '@/components/site/WhatsAppFloat.vue';
</script>

<template>
    <div class="flex min-h-screen flex-col bg-mk-paper text-mk-graphite-900">
        <a
            href="#conteudo"
            class="sr-only z-50 rounded bg-white px-4 py-2 text-sm font-semibold focus:not-sr-only focus:fixed focus:top-2 focus:left-2"
        >
            Pular para o conteúdo
        </a>
        <SiteHeader />
        <main id="conteudo" class="flex-1">
            <slot />
        </main>
        <SiteFooter />
        <WhatsAppFloat />
    </div>
</template>
