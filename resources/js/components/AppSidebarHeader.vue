<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Building2, UserRound } from '@lucide/vue';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';
import type { Auth } from '@/types/auth';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();
const auth = computed(() => page.props.auth as Auth);
const roleLabel = (role: string): string =>
    role === 'Funcionario' ? 'Funcionário' : role;
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center justify-between gap-3 border-b border-sidebar-border/70 px-4 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 print:hidden"
    >
        <div class="flex min-w-0 items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
        <div
            v-if="auth.company"
            class="flex min-w-0 items-center gap-3 text-sm"
            aria-label="Sessão atual"
        >
            <span
                class="hidden min-w-0 items-center gap-1.5 text-muted-foreground md:flex"
            >
                <Building2 class="size-4 shrink-0" aria-hidden="true" />
                <span class="truncate">{{ auth.company.name }}</span>
            </span>
            <span
                v-if="auth.companyUser"
                class="flex min-w-0 items-center gap-1.5 rounded-full border bg-card px-2.5 py-1"
            >
                <UserRound
                    class="size-4 shrink-0 text-primary"
                    aria-hidden="true"
                />
                <span
                    class="max-w-[9rem] truncate font-medium sm:max-w-[14rem]"
                    >{{ auth.companyUser.name }}</span
                >
                <span class="hidden text-xs text-muted-foreground sm:inline"
                    >· {{ roleLabel(auth.companyUser.role) }}</span
                >
            </span>
            <span
                v-else
                class="rounded-full border border-dashed px-2.5 py-1 text-xs text-muted-foreground"
                >Nenhum operador identificado</span
            >
        </div>
    </header>
</template>
