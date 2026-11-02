<script setup lang="ts">
import { Form, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Boxes, FileText, LayoutGrid, LockKeyhole, LogOut, Package, ReceiptText, ShoppingCart } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as productsIndex } from '@/routes/products';
import { index as cashierIndex } from '@/routes/pos';
import { index as expensesIndex } from '@/routes/expenses';
import { index as reportsIndex } from '@/routes/reports';
import { logout as companyLogout, portal as companyPortal } from '@/routes/company';
import { logout as companyUserLogout } from '@/routes/company/user';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

type WorkspaceNavItem = NavItem & { comingSoon?: boolean };

const mainNavItems: WorkspaceNavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Produtos',
        href: productsIndex(),
        icon: Package,
    },
    {
        title: 'Caixa',
        href: cashierIndex(),
        icon: ShoppingCart,
    },
    {
        title: 'Despesas',
        href: expensesIndex(),
        icon: ReceiptText,
    },
    {
        title: 'Estoque',
        href: dashboard(),
        icon: Boxes,
        comingSoon: true,
    },
    {
        title: 'Relatórios',
        href: reportsIndex(),
        icon: FileText,
    },
];

const page = usePage();
const hasCompany = computed(() => Boolean((page.props.auth as { company?: unknown }).company));
const hasCompanyUser = computed(() => Boolean((page.props.auth as { companyUser?: unknown }).companyUser));
const isCompanyWorkspaceLocked = computed(() => hasCompany.value && !hasCompanyUser.value);
const workspaceHome = computed(() => isCompanyWorkspaceLocked.value ? companyPortal() : dashboard());
const { isCurrentUrl } = useCurrentUrl();

</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="workspaceHome">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <SidebarGroup class="px-2 py-0">
                <SidebarGroupLabel>ÁREA DE TRABALHO</SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem v-for="item in mainNavItems" :key="item.title">
                        <SidebarMenuButton
                            v-if="!item.comingSoon && !isCompanyWorkspaceLocked"
                            as-child
                            :is-active="isCurrentUrl(item.href)"
                            :tooltip="item.title"
                        >
                            <Link :href="item.href">
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                            </Link>
                        </SidebarMenuButton>
                        <SidebarMenuButton
                            v-else
                            disabled
                            :tooltip="item.comingSoon ? `${item.title} em breve` : 'Entre com seu usuário para liberar os módulos'"
                        >
                            <component :is="item.icon" />
                            <span>{{ item.title }}<span v-if="item.comingSoon" class="ml-1 text-xs text-muted-foreground">Em breve</span></span>
                            <LockKeyhole class="ml-auto size-4 text-muted-foreground" aria-hidden="true" />
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter>
            <NavUser v-if="$page.props.auth.user" />
            <Form v-if="$page.props.auth.companyUser" v-bind="companyUserLogout.form()">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton type="submit" tooltip="Sair do usuário">
                            <LogOut />
                            <span>Sair do usuário</span>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </Form>
            <Form v-else-if="$page.props.auth.company" v-bind="companyLogout.form()">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton type="submit" tooltip="Sair da empresa">
                            <LogOut />
                            <span>Sair da empresa</span>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </Form>
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
