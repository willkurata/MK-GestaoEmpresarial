<script setup lang="ts">
import {
    ChevronLeft,
    ChevronRight,
    MessageCircle,
    Pause,
    Play,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import BrandBars from '@/components/site/BrandBars.vue';
import SiteButton from '@/components/site/SiteButton.vue';
import { useSite } from '@/composables/useSite';
import { whatsappUrl } from '@/lib/site';
import type { RouteDefinition } from '@/wayfinder';

export type CarouselSlide = {
    id: string;
    eyebrow: string;
    title: string;
    description: string;
    icon: Component;
    /** Caminho da imagem a ser exibida no slide (opcional; quando presente, substitui o ícone). */
    image?: string;
    cta: { label: string; href: RouteDefinition<'get'> };
    /** Mensagem pré-preenchida do botão secundário de WhatsApp (opcional). */
    whatsappMessage?: string;
    tone: 'graphite' | 'green' | 'forest';
};

/**
 * Carrossel de destaques da home, em tela cheia como um banner/anúncio
 * (altura da janela menos o header fixo, limitada a 56rem em telas muito altas). Acessível por padrão: gira sozinho a cada
 * `interval` ms, pausa ao passar o mouse ou focar no conteúdo, tem botão de
 * pausa explícito e não gira automaticamente para quem pediu menos movimento
 * (prefers-reduced-motion). Cada slide leva a uma página do site, nunca
 * direto ao sistema MK Gestão.
 */
const { slides, interval = 7000 } = defineProps<{
    slides: CarouselSlide[];
    interval?: number;
}>();

const site = useSite();
const current = ref(0);
const paused = ref(false);
const hovering = ref(false);
const focusWithin = ref(false);
const reducedMotion = ref(false);
let timer: ReturnType<typeof setInterval> | undefined;

const rotating = computed(
    () =>
        slides.length > 1 &&
        !paused.value &&
        !hovering.value &&
        !focusWithin.value &&
        !reducedMotion.value,
);

const tones = {
    graphite: {
        section:
            'bg-mk-graphite-950 bg-[radial-gradient(ellipse_at_top_right,var(--color-mk-green-900),transparent_60%)] text-white',
        eyebrow: 'text-mk-green-300',
        text: 'text-mk-graphite-200',
        bars: 'text-mk-green-700/60',
        icon: 'text-mk-green-300',
    },
    green: {
        section:
            'bg-mk-green-800 bg-[radial-gradient(ellipse_at_bottom_left,var(--color-mk-green-950),transparent_65%)] text-white',
        eyebrow: 'text-mk-green-200',
        text: 'text-mk-green-50',
        bars: 'text-mk-green-600/60',
        icon: 'text-mk-green-200',
    },
    forest: {
        section:
            'bg-mk-green-950 bg-[radial-gradient(ellipse_at_top_left,var(--color-mk-green-700),transparent_60%)] text-white',
        eyebrow: 'text-mk-green-300',
        text: 'text-mk-green-100',
        bars: 'text-mk-green-800/70',
        icon: 'text-mk-green-300',
    },
} as const;

function go(index: number): void {
    current.value = (index + slides.length) % slides.length;
}

function next(): void {
    go(current.value + 1);
}

function previous(): void {
    go(current.value - 1);
}

function onFocusOut(event: FocusEvent): void {
    const target = event.currentTarget as HTMLElement;
    focusWithin.value = target.contains(event.relatedTarget as Node | null);
}

onMounted(() => {
    const query = window.matchMedia?.('(prefers-reduced-motion: reduce)');
    reducedMotion.value = query?.matches ?? false;
    timer = setInterval(() => {
        if (rotating.value) {
            next();
        }
    }, interval);
});

onUnmounted(() => clearInterval(timer));
</script>

<template>
    <div
        class="relative"
        role="region"
        aria-roledescription="carrossel"
        aria-label="Destaques da MK Negócios"
        @mouseenter="hovering = true"
        @mouseleave="hovering = false"
        @focusin="focusWithin = true"
        @focusout="onFocusOut"
    >
        <div
            :aria-live="rotating ? 'off' : 'polite'"
            class="grid grid-cols-[minmax(0,1fr)] [&>*]:col-start-1 [&>*]:row-start-1"
        >
            <section
                v-for="(slide, index) in slides"
                :id="`destaque-${slide.id}`"
                :key="slide.id"
                role="group"
                aria-roledescription="slide"
                :aria-label="`${index + 1} de ${slides.length}: ${slide.eyebrow}`"
                :aria-hidden="index !== current"
                :inert="index !== current"
                class="relative flex min-h-[min(calc(100svh-4rem),56rem)] items-center overflow-hidden transition-opacity duration-700 motion-reduce:transition-none"
                :class="[
                    tones[slide.tone].section,
                    index === current
                        ? 'opacity-100'
                        : 'pointer-events-none opacity-0',
                ]"
            >
                <BrandBars
                    class="absolute -right-10 bottom-0 size-[20rem] sm:size-[32rem]"
                    :class="tones[slide.tone].bars"
                />
                <div
                    class="relative mx-auto grid w-full max-w-6xl gap-8 px-4 pt-16 pb-32 sm:px-6 sm:pt-20 md:grid-cols-[1fr_auto] md:items-center"
                >
                    <div>
                        <p
                            class="text-xs font-semibold tracking-[0.25em] uppercase"
                            :class="tones[slide.tone].eyebrow"
                        >
                            {{ slide.eyebrow }}
                        </p>
                        <h2
                            class="mt-5 max-w-3xl font-display text-4xl leading-[1.05] font-semibold sm:text-6xl lg:text-7xl"
                        >
                            {{ slide.title }}
                        </h2>
                        <p
                            class="mt-6 max-w-xl text-lg leading-relaxed"
                            :class="tones[slide.tone].text"
                        >
                            {{ slide.description }}
                        </p>
                        <div class="mt-10 flex flex-wrap gap-3">
                            <SiteButton :href="slide.cta.href" variant="light">
                                {{ slide.cta.label }}
                                <ChevronRight
                                    class="size-4"
                                    aria-hidden="true"
                                />
                            </SiteButton>
                            <a
                                v-if="slide.whatsappMessage"
                                :href="
                                    whatsappUrl(
                                        site.contact.whatsapp,
                                        slide.whatsappMessage,
                                    )
                                "
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-full border border-white/40 px-6 text-sm font-semibold text-white transition-colors hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
                            >
                                <MessageCircle
                                    class="size-4"
                                    aria-hidden="true"
                                />
                                Falar pelo WhatsApp
                            </a>
                        </div>
                    </div>
                    <img
                        v-if="slide.image"
                        :src="slide.image"
                        :alt="slide.title"
                        class="hidden size-40 md:block lg:size-60 object-cover object-center"
                        :class="tones[slide.tone].icon"
                        aria-hidden="true"
                    />
                    <component
                        v-else
                        :is="slide.icon"
                        class="hidden size-40 md:block lg:size-60"
                        :class="tones[slide.tone].icon"
                        stroke-width="1"
                        aria-hidden="true"
                    />
                </div>
            </section>
        </div>

        <div
            v-if="slides.length > 1"
            class="absolute inset-x-0 bottom-8 mx-auto flex max-w-6xl items-center gap-3 px-4 sm:px-6"
        >
            <button
                type="button"
                class="inline-flex size-11 items-center justify-center rounded-full bg-white/90 text-mk-graphite-900 shadow-sm hover:bg-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-mk-green-700"
                :aria-label="
                    paused ? 'Retomar rotação automática' : 'Pausar destaques'
                "
                @click="paused = !paused"
            >
                <Play v-if="paused" class="size-4" aria-hidden="true" />
                <Pause v-else class="size-4" aria-hidden="true" />
            </button>
            <button
                type="button"
                class="inline-flex size-11 items-center justify-center rounded-full bg-white/90 text-mk-graphite-900 shadow-sm hover:bg-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-mk-green-700"
                aria-label="Destaque anterior"
                @click="previous"
            >
                <ChevronLeft class="size-5" aria-hidden="true" />
            </button>
            <button
                type="button"
                class="inline-flex size-11 items-center justify-center rounded-full bg-white/90 text-mk-graphite-900 shadow-sm hover:bg-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-mk-green-700"
                aria-label="Próximo destaque"
                @click="next"
            >
                <ChevronRight class="size-5" aria-hidden="true" />
            </button>
            <div class="ml-2 flex items-center">
                <button
                    v-for="(slide, index) in slides"
                    :key="slide.id"
                    type="button"
                    class="group inline-flex size-11 items-center justify-center focus-visible:outline-2 focus-visible:outline-offset-[-6px] focus-visible:outline-mk-green-700"
                    :aria-label="`Ir para o destaque ${index + 1}: ${slide.eyebrow}`"
                    :aria-current="index === current ? 'true' : undefined"
                    :aria-controls="`destaque-${slide.id}`"
                    @click="go(index)"
                >
                    <span
                        class="block h-2 rounded-full bg-white shadow-sm transition-all"
                        :class="
                            index === current
                                ? 'w-8 opacity-100'
                                : 'w-2 opacity-60 group-hover:opacity-90'
                        "
                    />
                </button>
            </div>
        </div>
    </div>
</template>
