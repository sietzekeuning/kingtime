<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import FaIcon from '@/components/FaIcon.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    useSidebar,
} from '@/components/ui/sidebar';
import { dashboard, home } from '@/routes';
import clients from '@/routes/clients';
import { edit as editIntegrations } from '@/routes/integrations';
import invoices from '@/routes/invoices';
import { edit as editProfile } from '@/routes/profile';
import projects from '@/routes/projects';
import reports from '@/routes/reports';
import timeEntries from '@/routes/time-entries';
import type { NavItem } from '@/types';

const { toggleSidebar } = useSidebar();

const mainNavItems: NavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: 'house' },
    { title: 'Reports', href: reports.index(), icon: 'chart-simple' },
    { title: 'Time entries', href: timeEntries.index(), icon: 'clock' },
    { title: 'Projects', href: projects.index(), icon: 'briefcase' },
    { title: 'Clients', href: clients.index(), icon: 'building' },
    { title: 'Invoices', href: invoices.index(), icon: 'file-invoice' },
];

const otherNavItems: NavItem[] = [
    { title: 'Integrations', href: editIntegrations(), icon: 'plug' },
    { title: 'Settings', href: editProfile(), icon: 'gear' },
];

const footerNavItems: NavItem[] = [
    { title: 'Homepage', href: home(), icon: 'globe' },
    {
        title: 'Repository',
        href: 'https://github.com/sietzekeuning/kingtime',
        icon: 'github',
    },
    {
        title: 'Documentation',
        href: 'https://github.com/sietzekeuning/kingtime#readme',
        icon: 'book',
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar">
        <SidebarHeader
            class="border-sidebar-border h-16 shrink-0 justify-center border-b px-3 py-0 group-data-[collapsible=icon]:px-2"
        >
            <div
                class="flex h-9 items-center gap-2 group-data-[collapsible=icon]:justify-center"
            >
                <Link
                    :href="dashboard()"
                    class="flex min-w-0 items-center gap-2.5"
                    title="Dashboard"
                >
                    <span
                        class="bg-primary text-primary-foreground flex size-8 shrink-0 items-center justify-center rounded-lg"
                    >
                        <AppLogoIcon class="size-5" />
                    </span>
                    <span
                        class="truncate text-lg font-bold tracking-tight group-data-[collapsible=icon]:hidden"
                    >
                        KingTime
                    </span>
                </Link>
                <button
                    type="button"
                    class="border-border bg-card text-muted-foreground hover:text-foreground hover:bg-sidebar-accent ml-auto flex size-8 shrink-0 items-center justify-center rounded-lg border transition-colors group-data-[collapsible=icon]:hidden"
                    title="Collapse sidebar"
                    @click="toggleSidebar"
                >
                    <FaIcon icon="sidebar" class="text-sm" />
                    <span class="sr-only">Collapse sidebar</span>
                </button>
            </div>
        </SidebarHeader>

        <SidebarContent class="gap-5 pt-2">
            <NavMain :items="mainNavItems" label="General" />
            <NavMain :items="otherNavItems" label="Other" />
        </SidebarContent>

        <SidebarFooter class="p-3">
            <NavFooter :items="footerNavItems" />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
