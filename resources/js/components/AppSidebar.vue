<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { BookOpen, CalendarDays, LayoutGrid, Users } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
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
import { index as friendsIndex } from '@/routes/friends';
import { index as krsIndex } from '@/routes/krs';
import { index as adminOfferings } from '@/routes/krs/admin/offerings';
import type { NavItem } from '@/types';

const page = usePage();
const isAdmin = computed(() => Boolean(page.props.auth.user?.is_admin));

const mainNavItems = computed((): NavItem[] => {
    const items: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: 'KRS Planner',
            href: krsIndex(),
            icon: CalendarDays,
        },
        {
            title: 'Teman',
            href: friendsIndex(),
            icon: Users,
        },
    ];

    if (isAdmin.value) {
        items.push({
            title: 'Kelola Katalog',
            href: adminOfferings(),
            icon: BookOpen,
        });
    }

    return items;
});

const footerNavItems: NavItem[] = [];
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
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
