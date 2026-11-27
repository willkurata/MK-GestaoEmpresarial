<script setup lang="ts">
import { BellRing, Mail, MessageCircle, Send, Smartphone } from '@lucide/vue';
import { computed, ref } from 'vue';
import Card from '../../components/Card.vue';
import AdminLayout from '../../layouts/AdminLayout.vue';
import { formatShortDate, formatTime } from '../../lib/format';
import { audienceStudents, sendCampaign, state } from '../../lib/store';
import { showToast } from '../../lib/toast';
import type { Campaign, CampaignAudience } from '../../lib/types';

const audiences: { value: CampaignAudience; label: string }[] = [
    { value: 'todos', label: 'Todos os alunos' },
    { value: 'ativos', label: 'Alunos em dia' },
    { value: 'inadimplentes', label: 'Inadimplentes' },
    { value: 'em_risco', label: 'Em risco de evasão' },
];

const channels: {
    value: Campaign['channel'];
    label: string;
    icon: typeof Mail;
}[] = [
    { value: 'push', label: 'Push no app', icon: Smartphone },
    { value: 'whatsapp', label: 'WhatsApp', icon: MessageCircle },
    { value: 'email', label: 'E-mail', icon: Mail },
];

const templates = [
    {
        title: 'Traga um amigo',
        message: 'Indique um amigo e ganhe 50% na próxima mensalidade.',
        audience: 'ativos' as const,
    },
    {
        title: 'Sentimos sua falta',
        message:
            'Faz tempo que você não aparece! Reserve uma aula e volte com tudo.',
        audience: 'em_risco' as const,
    },
    {
        title: 'Regularize com Pix',
        message:
            'Sua mensalidade está em aberto. Pague pelo app em segundos e treine hoje.',
        audience: 'inadimplentes' as const,
    },
];

const title = ref('');
const message = ref('');
const audience = ref<CampaignAudience>('todos');
const channel = ref<Campaign['channel']>('push');

const reach = computed(() => audienceStudents(audience.value).length);
const canSend = computed(
    () => title.value.trim().length > 2 && message.value.trim().length > 5,
);

function useTemplate(template: (typeof templates)[number]): void {
    title.value = template.title;
    message.value = template.message;
    audience.value = template.audience;
}

function send(): void {
    if (!canSend.value) {
        return;
    }

    const campaign = sendCampaign({
        title: title.value.trim(),
        message: message.value.trim(),
        audience: audience.value,
        channel: channel.value,
    });
    showToast({
        title: `Campanha registrada para ${campaign.recipients} alunos`,
        description:
            'Demonstração: nenhuma mensagem real foi enviada. Quem desativou promoções não recebe.',
        tone: 'ok',
    });
    title.value = '';
    message.value = '';
}

/** Régua automática de cobrança e lembretes (configuração; disparo é do backend). */
const automations = [
    {
        label: 'Mensalidade vence em 3 dias',
        when: 'D-3 · 9h',
        channel: 'Push + WhatsApp',
    },
    {
        label: 'Mensalidade vence hoje',
        when: 'D0 · 9h',
        channel: 'Push + WhatsApp',
    },
    { label: 'Mensalidade em atraso', when: 'D+3 · 10h', channel: 'WhatsApp' },
    {
        label: 'Falha no Pix Automático',
        when: 'Na hora',
        channel: 'Push + e-mail',
    },
    { label: 'Lembrete de aula reservada', when: '1h antes', channel: 'Push' },
    { label: 'Vaga liberada na fila', when: 'Na hora', channel: 'Push' },
    {
        label: 'Sentimos sua falta',
        when: '14 dias sem treinar',
        channel: 'WhatsApp',
    },
];
</script>

<template>
    <AdminLayout
        title="Campanhas e notificações"
        subtitle="Promoções segmentadas e régua automática"
    >
        <div class="grid gap-4 lg:grid-cols-5 lg:gap-6">
            <Card class="lg:col-span-3" title="Nova campanha">
                <div class="mb-4 flex flex-wrap gap-2">
                    <button
                        v-for="template in templates"
                        :key="template.title"
                        type="button"
                        class="border-line rounded-full border px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50"
                        @click="useTemplate(template)"
                    >
                        Modelo: {{ template.title }}
                    </button>
                </div>
                <form class="space-y-4" @submit.prevent="send">
                    <label class="block">
                        <span class="text-sm font-medium">Título</span>
                        <input
                            v-model="title"
                            type="text"
                            maxlength="60"
                            class="border-line mt-1 h-10 w-full rounded-lg border px-3 text-sm outline-none focus:border-slate-400"
                        />
                    </label>
                    <label class="block">
                        <span class="text-sm font-medium">Mensagem</span>
                        <textarea
                            v-model="message"
                            rows="3"
                            maxlength="240"
                            class="border-line mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-slate-400"
                        />
                        <span class="tabular text-xs text-slate-400"
                            >{{ message.length }}/240</span
                        >
                    </label>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="text-sm font-medium">Público</span>
                            <select
                                v-model="audience"
                                class="border-line mt-1 h-10 w-full rounded-lg border bg-white px-3 text-sm"
                            >
                                <option
                                    v-for="option in audiences"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                        </label>
                        <fieldset>
                            <legend class="text-sm font-medium">Canal</legend>
                            <div class="mt-1 flex gap-1.5">
                                <button
                                    v-for="option in channels"
                                    :key="option.value"
                                    type="button"
                                    class="flex h-10 flex-1 items-center justify-center gap-1.5 rounded-lg border text-xs font-medium"
                                    :class="
                                        channel === option.value
                                            ? 'border-ink-950 bg-ink-950 text-white'
                                            : 'border-line text-slate-600'
                                    "
                                    :aria-pressed="channel === option.value"
                                    @click="channel = option.value"
                                >
                                    <component
                                        :is="option.icon"
                                        class="size-3.5"
                                    />{{ option.label }}
                                </button>
                            </div>
                        </fieldset>
                    </div>

                    <!-- Pré-visualização -->
                    <div class="bg-ink-950 text-ink-100 rounded-2xl p-4">
                        <p
                            class="text-ink-400 mb-2 text-[11px] tracking-wider uppercase"
                        >
                            Pré-visualização
                        </p>
                        <div class="bg-ink-800 flex gap-3 rounded-xl p-3">
                            <BellRing class="size-5 shrink-0 text-lime-400" />
                            <div>
                                <p class="text-sm font-semibold">
                                    {{ title || 'Título da campanha' }}
                                </p>
                                <p class="text-ink-300 text-sm">
                                    {{ message || 'A mensagem aparece aqui.' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <p class="text-sm text-slate-600">
                            Alcance estimado:
                            <strong class="tabular">{{ reach }}</strong> alunos
                        </p>
                        <button
                            type="submit"
                            class="bg-ink-950 flex min-h-10 items-center gap-2 rounded-lg px-4 text-sm font-semibold text-white disabled:opacity-40"
                            :disabled="!canSend"
                        >
                            <Send class="size-4" /> Enviar campanha
                        </button>
                    </div>
                </form>
            </Card>

            <Card
                class="lg:col-span-2"
                title="Régua automática"
                description="Disparada pelo sistema; o aluno controla as preferências no app"
            >
                <ul class="divide-y divide-slate-100">
                    <li
                        v-for="automation in automations"
                        :key="automation.label"
                        class="flex items-center justify-between gap-3 py-2.5"
                    >
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">
                                {{ automation.label }}
                            </p>
                            <p class="text-xs text-slate-500">
                                {{ automation.when }} · {{ automation.channel }}
                            </p>
                        </div>
                        <span
                            class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-800"
                            >Ativa</span
                        >
                    </li>
                </ul>
            </Card>
        </div>

        <Card title="Enviadas">
            <ul v-if="state.campaigns.length" class="divide-y divide-slate-100">
                <li
                    v-for="campaign in state.campaigns"
                    :key="campaign.id"
                    class="flex items-center justify-between gap-3 py-2.5 text-sm"
                >
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ campaign.title }}</p>
                        <p class="truncate text-xs text-slate-500">
                            {{ campaign.message }}
                        </p>
                    </div>
                    <span class="tabular shrink-0 text-xs text-slate-500">
                        {{ campaign.recipients }} alunos ·
                        {{ formatShortDate(campaign.sentAt) }}
                        {{ formatTime(campaign.sentAt) }}
                    </span>
                </li>
            </ul>
            <p v-else class="py-6 text-center text-sm text-slate-500">
                Nenhuma campanha enviada ainda.
            </p>
        </Card>
    </AdminLayout>
</template>
