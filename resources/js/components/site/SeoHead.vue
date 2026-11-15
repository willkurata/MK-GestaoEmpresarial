<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useSite } from '@/composables/useSite';

const props = defineProps<{
    title: string;
    description: string;
    image?: string | null;
}>();

const site = useSite();
const page = usePage();
/** URL canônica: a página atual sem query string (filtros, paginação). */
const canonical = computed(() => `${site.value.url}${page.url.split('?')[0]}`);
const image = computed(
    () => props.image ?? `${site.value.url}/logo-mk-negocios.png`,
);
</script>

<template>
    <Head :title="title">
        <meta
            head-key="description"
            name="description"
            :content="description"
        />
        <meta
            head-key="og:title"
            property="og:title"
            :content="`${title} - MK Negócios`"
        />
        <meta
            head-key="og:description"
            property="og:description"
            :content="description"
        />
        <meta head-key="og:type" property="og:type" content="website" />
        <meta head-key="og:image" property="og:image" :content="image" />
        <meta head-key="og:url" property="og:url" :content="canonical" />
        <link head-key="canonical" rel="canonical" :href="canonical" />
    </Head>
</template>
