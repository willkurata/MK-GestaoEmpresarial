<script setup lang="ts">
import { watchEffect } from 'vue';
import ToastHost from './components/ToastHost.vue';
import { currentRoute } from './lib/router';

watchEffect(() => {
    document.title = `${currentRoute.value.title} · MK Academia`;
});
</script>

<template>
    <div class="min-h-dvh">
        <component :is="currentRoute.component" :key="currentRoute.path" />
        <ToastHost />
    </div>
</template>
