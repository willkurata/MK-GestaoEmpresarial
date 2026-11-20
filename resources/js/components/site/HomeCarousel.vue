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
    /** Foto do slide (opcional). Sem foto, o painel mostra o ícone da área. */
    image?: string;
    /** Texto alternativo da foto; vazio quando ela é só decorativa. */
    imageAlt?: string;
    cta: { label: string; href: RouteDefinition<'get'> };
    /** Mensagem pré-preenchida do botão secundário de WhatsApp (opcional). */
    whatsappMessage?: string;
    tone: 'sand' | 'sage' | 'mist';
};

/**
 * Carrossel de destaques da home (WIL-28/WIL-29), logo abaixo do hero com
 * busca: altura do conteúdo (não mais tela cheia), fundo claro e suave, texto
 * escuro e um painel arredondado à direita com a foto (Imóveis) ou o ícone
 * da área. Gira sozinho a cada `interval` ms, pausa ao passar o mouse ou
 * focar no conteúdo, tem botão de pausa explícito e não gira para quem pediu
 * menos movimento (prefers-reduced-motion). Cada slide leva a uma página do
 * site, nunca direto ao sistema MK Gestão.
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

/* Variações de fundo bem sutis; o verde MK fica nos detalhes e no CTA. */
const tones = {
    sand: {
        section:
            'bg-mk-paper bg-[radial-gradient(ellipse_at_85%_20%,var(--color-mk-green-100),transparent_55%)]',
        panel: 'bg-mk-sand',
    },
    sage: {
        section:
            'bg-mk-paper bg-[radial-gradient(ellipse_at_15%_80%,var(--color-mk-green-100),transparent_55%)]',
        panel: 'bg-mk-green-100',
    },
    mist: {
        section:
            'bg-mk-paper bg-[radial-gradient(ellipse_at_80%_85%,var(--color-mk-green-50),transparent_60%)]',
        panel: 'bg-mk-green-50',
    },
} as const;

const controlClass =
    'inline-flex size-11 items-center justify-center rounded-full border border-mk-graphite-200 bg-white text-mk-graphite-800 transition-colors hover:border-mk-green-300 hover:text-mk-green-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-mk-green-700';

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
                class="relative flex items-center overflow-hidden transition-opacity duration-1000 ease-out motion-reduce:transition-none"
                :class="[
                    tones[slide.tone].section,
                    index === current
                        ? 'opacity-100'
                        : 'pointer-events-none opacity-0',
                ]"
            >
                <div
                    class="relative mx-auto grid w-full max-w-7xl gap-12 px-4 pt-20 pb-32 sm:px-6 lg:grid-cols-[1.05fr_1fr] lg:items-center lg:gap-20 lg:px-8 lg:pt-28 lg:pb-36"
                >
                    <div
                        class="transition duration-1000 ease-out motion-reduce:transition-none"
                        :class="
                            index === current
                                ? 'translate-y-0'
                                : 'translate-y-4 motion-reduce:translate-y-0'
                        "
                    >
                        <p
                            class="inline-flex items-center gap-2 rounded-full border border-mk-green-200 bg-white/70 px-4 py-1.5 text-[0.7rem] font-semibold tracking-[0.12em] text-mk-green-700 uppercase sm:text-xs sm:tracking-[0.2em]"
                        >
                            <component
                                :is="slide.icon"
                                class="size-3.5"
                                aria-hidden="true"
                            />
                            {{ slide.eyebrow }}
                        </p>
                        <h2
                            class="mt-7 max-w-2xl text-4xl leading-[1.1] font-medium tracking-tight text-balance text-mk-graphite-950 sm:text-5xl lg:text-6xl"
                        >
                            {{ slide.title }}
                        </h2>
                        <p
                            class="mt-6 max-w-xl text-lg leading-8 text-mk-graphite-600"
                        >
                            {{ slide.description }}
                        </p>
                        <div class="mt-10 flex flex-wrap gap-3">
                            <SiteButton :href="slide.cta.href">
                                {{ slide.cta.label }}
                                <ChevronRight
                                    class="size-4"
                                    aria-hidden="true"
                                />
                            </SiteButton>
                            <SiteButton
                                v-if="slide.whatsappMessage"
                                :href="
                                    whatsappUrl(
                                        site.contact.whatsapp,
                                        slide.whatsappMessage,
                                    )
                                "
                                variant="secondary"
                                external
                            >
                                <MessageCircle
                                    class="size-4"
                                    aria-hidden="true"
                                />
                                Falar pelo WhatsApp
                            </SiteButton>
                        </div>
                    </div>

                    <div
                        class="relative aspect-[16/10] overflow-hidden rounded-[2rem] lg:aspect-[5/4]"
                        :class="tones[slide.tone].panel"
                    >
                        <img
                            v-if="slide.image"
                            :src="slide.image"
                            :alt="slide.imageAlt ?? ''"
                            :loading="index === 0 ? 'eager' : 'lazy'"
                            :fetchpriority="index === 0 ? 'high' : 'auto'"
                            decoding="async"
                            class="size-full object-cover transition-transform duration-[7000ms] ease-out motion-reduce:transition-none"
                            :class="
                                index === current ? 'scale-105' : 'scale-100'
                            "
                        />
                        <template v-else>
                            <BrandBars
                                class="absolute -right-8 -bottom-6 size-[18rem] text-mk-green-300/50 sm:size-[24rem]"
                            />
                            <div
                                class="absolute inset-0 flex items-center justify-center"
                            >
                                <span
                                    class="inline-flex size-32 items-center justify-center rounded-[2rem] bg-white text-mk-green-700 shadow-xl shadow-mk-green-900/5 sm:size-40"
                                >
                                    <component
                                        :is="slide.icon"
                                        class="size-14 sm:size-16"
                                        stroke-width="1.25"
                                        aria-hidden="true"
                                    />
                                </span>
                            </div>
                        </template>
                    </div>
                </div>
            </section>
        </div>

        <div
            v-if="slides.length > 1"
            class="absolute inset-x-0 bottom-8 mx-auto flex max-w-7xl items-center gap-3 px-4 sm:px-6 lg:px-8"
        >
            <button
                type="button"
                :class="controlClass"
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
                :class="controlClass"
                aria-label="Destaque anterior"
                @click="previous"
            >
                <ChevronLeft class="size-5" aria-hidden="true" />
            </button>
            <button
                type="button"
                :class="controlClass"
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
                        class="block h-1.5 rounded-full transition-all"
                        :class="
                            index === current
                                ? 'w-8 bg-mk-green-700'
                                : 'w-1.5 bg-mk-graphite-300 group-hover:bg-mk-graphite-400'
                        "
                    />
                </button>
            </div>
        </div>
    </div>
</template>
