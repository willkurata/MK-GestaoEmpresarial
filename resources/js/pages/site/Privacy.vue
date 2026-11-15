<script setup lang="ts">
import PageHero from '@/components/site/PageHero.vue';
import SeoHead from '@/components/site/SeoHead.vue';
import { useSite } from '@/composables/useSite';

const site = useSite();
</script>

<template>
    <SeoHead
        title="Política de privacidade"
        description="Como a MK Negócios trata dados pessoais de visitantes e clientes, conforme a Lei Geral de Proteção de Dados (LGPD)."
    />

    <PageHero
        eyebrow="Privacidade"
        title="Política de privacidade"
        description="Como tratamos os dados pessoais de quem visita o site e entra em contato com a MK Negócios, conforme a Lei nº 13.709/2018 (LGPD)."
    />

    <article
        class="mx-auto max-w-3xl px-4 py-16 leading-relaxed text-mk-graphite-700 sm:px-6 [&_h2]:mt-10 [&_h2]:font-display [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:text-mk-graphite-950 [&_li]:mt-1 [&_p]:mt-4 [&_ul]:mt-4 [&_ul]:list-disc [&_ul]:pl-6"
    >
        <p class="!mt-0 text-sm text-mk-graphite-500">
            Versão inicial — novembro de 2026.
        </p>

        <h2>1. Quem somos</h2>
        <p>
            A MK Negócios atua com imóveis, contabilidade e soluções para
            empresas em Ubatuba e Caraguatatuba (SP) e é a controladora dos
            dados pessoais tratados por meio deste site.
        </p>

        <h2>2. Quais dados tratamos</h2>
        <ul>
            <li>
                Dados que você nos envia ao entrar em contato (nome, telefone,
                e-mail e o conteúdo da mensagem, incluindo o imóvel ou serviço
                de interesse).
            </li>
            <li>
                Dados técnicos necessários ao funcionamento do site, como
                endereço IP e cookies de sessão e de segurança.
            </li>
        </ul>

        <h2>3. Para que usamos</h2>
        <ul>
            <li>
                Responder ao seu contato e prestar o atendimento solicitado.
            </li>
            <li>
                Apresentar imóveis e serviços relacionados ao seu interesse.
            </li>
            <li>Manter a segurança do site e cumprir obrigações legais.</li>
        </ul>

        <h2>4. Compartilhamento</h2>
        <p>
            Não vendemos dados pessoais. Podemos compartilhá-los apenas com
            prestadores necessários à operação (como hospedagem e e-mail), ou
            quando exigido por lei ou autoridade competente. Conversas pelo
            WhatsApp também estão sujeitas à política de privacidade desse
            aplicativo.
        </p>

        <h2>5. Por quanto tempo guardamos</h2>
        <p>
            Mantemos os dados pelo tempo necessário ao atendimento e ao
            cumprimento de obrigações legais, eliminando-os ou anonimizando-os
            depois disso.
        </p>

        <h2>6. Seus direitos</h2>
        <p>
            Você pode solicitar confirmação de tratamento, acesso, correção,
            anonimização, portabilidade ou eliminação dos seus dados, além de
            revogar consentimentos, escrevendo para
            <a
                :href="`mailto:${site.contact.email}`"
                class="font-medium text-mk-green-700 underline underline-offset-4"
                >{{ site.contact.email }}</a
            >.
        </p>

        <h2>7. Alterações</h2>
        <p>
            Esta política pode ser atualizada. A versão vigente estará sempre
            disponível nesta página.
        </p>
    </article>
</template>
