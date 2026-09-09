<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BookOpen,
    Briefcase,
    Building2,
    Clock,
    FolderGit2,
    LayoutGrid,
    ListChecks,
    Plug,
    Receipt,
    Settings,
} from '@lucide/vue';
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
import clients from '@/routes/clients';
import { edit as editIntegrations } from '@/routes/integrations';
import invoices from '@/routes/invoices';
import { edit as editProfile } from '@/routes/profile';
import projects from '@/routes/projects';
import tasks from '@/routes/tasks';
import timeEntries from '@/routes/time-entries';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
    { title: 'Time entries', href: timeEntries.index(), icon: Clock },
    { title: 'Projects', href: projects.index(), icon: Briefcase },
    { title: 'Clients', href: clients.index(), icon: Building2 },
    { title: 'Tasks', href: tasks.index(), icon: ListChecks },
    { title: 'Invoices', href: invoices.index(), icon: Receipt },
];

const otherNavItems: NavItem[] = [
    { title: 'Integrations', href: editIntegrations(), icon: Plug },
    { title: 'Settings', href: editProfile(), icon: Settings },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/sietzekeuning/kingtime',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://github.com/sietzekeuning/kingtime#readme',
        icon: BookOpen,
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

        <SidebarContent class="gap-4">
            <NavMain :items="mainNavItems" label="General" />
            <NavMain :items="otherNavItems" label="Other" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
