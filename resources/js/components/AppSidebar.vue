<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import { LayoutGrid, LogOut, Package, ReceiptText, ShoppingCart } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as productsIndex } from '@/routes/products';
import { index as cashierIndex } from '@/routes/pos';
import { index as expensesIndex } from '@/routes/expenses';
import { logout as companyUserLogout } from '@/routes/company/user';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
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
];

</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
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
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
