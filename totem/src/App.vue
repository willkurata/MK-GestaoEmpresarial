<script setup lang="ts">
import DemoHome from '@totem/pages/DemoHome.vue';
import EntryPage from '@totem/pages/EntryPage.vue';
import KioskScreen from '@totem/pages/KioskScreen.vue';
import OrderPage from '@totem/pages/OrderPage.vue';
import { route } from '@totem/router';
</script>

<template>
    <KioskScreen
        v-if="route.name === 'kiosk'"
        :key="route.totemCode"
        :totem-code="route.totemCode"
    />
    <EntryPage
        v-else-if="route.name === 'entry'"
        :key="`${route.totemCode}-${route.token}`"
        :totem-code="route.totemCode"
        :token="route.token"
    />
    <OrderPage
        v-else-if="route.name === 'order'"
        :key="route.orderId"
        :order-id="route.orderId"
    />
    <DemoHome v-else-if="route.name === 'home'" />
    <main
        v-else
        class="mx-auto flex min-h-dvh max-w-md flex-col items-center justify-center gap-3 px-4 text-center"
    >
        <h1 class="font-display text-2xl font-extrabold">
            Página não encontrada
        </h1>
        <p class="text-ink-soft">
            Escaneie o QR Code na tela do totem para começar.
        </p>
    </main>
</template>
