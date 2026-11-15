<script setup lang="ts">
import { MessageCircle } from '@lucide/vue';
import { computed } from 'vue';
import { useSite, useWhatsappContext } from '@/composables/useSite';
import { whatsappUrl } from '@/lib/site';

const site = useSite();
const contextMessage = useWhatsappContext();
const href = computed(() =>
    whatsappUrl(
        site.value.contact.whatsapp,
        contextMessage.value ?? 'Olá! Vim pelo site da MK Negócios.',
    ),
);
</script>

<template>
    <a
        :href="href"
        target="_blank"
        rel="noopener noreferrer"
        class="fixed right-4 bottom-4 z-40 inline-flex size-14 items-center justify-center rounded-full bg-[#1f7a4d] text-white shadow-lg shadow-mk-graphite-950/20 transition-transform hover:scale-105 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1f7a4d]"
    >
        <MessageCircle class="size-7" aria-hidden="true" />
        <span class="sr-only">Conversar pelo WhatsApp</span>
    </a>
</template>
