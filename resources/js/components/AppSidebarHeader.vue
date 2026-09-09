<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import FaIcon from '@/components/FaIcon.vue';
import RunningTimerWidget from '@/components/time/RunningTimerWidget.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useInitials } from '@/composables/useInitials';
import timeEntries from '@/routes/time-entries';
import type { BreadcrumbItem } from '@/types';

/**
 * Top bar of the content panel: where you are (breadcrumb pill), the
 * running timer, a shortcut to log time and the user menu. The sidebar
 * toggle only shows when the sidebar is collapsed to its icon rail or on
 * mobile; expanded, the sidebar carries its own collapse button.
 */
withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();
const user = computed(() => page.props.auth.user);
const { getInitials } = useInitials();
</script>

<template>
    <header
        class="border-border flex h-16 shrink-0 items-center gap-3 border-b px-4 md:px-5"
    >
        <SidebarTrigger
            class="-ml-1 md:hidden md:group-has-data-[collapsible=icon]/sidebar-wrapper:inline-flex"
        />
        <Breadcrumbs v-if="breadcrumbs.length > 0" :breadcrumbs="breadcrumbs" />

        <div class="ml-auto flex items-center gap-2">
            <RunningTimerWidget />
            <Button variant="outline" class="hidden sm:inline-flex" as-child>
                <Link :href="timeEntries.create()">
                    <FaIcon icon="plus" class="text-sm" />
                    Log time
                </Link>
            </Button>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <button
                        type="button"
                        class="focus-visible:ring-ring/50 rounded-full outline-none focus-visible:ring-[3px]"
                        data-test="user-menu-button"
                    >
                        <Avatar class="size-9 overflow-hidden rounded-full">
                            <AvatarImage
                                v-if="user.avatar"
                                :src="user.avatar"
                                :alt="user.name"
                            />
                            <AvatarFallback
                                class="bg-muted text-foreground rounded-full text-sm font-semibold"
                            >
                                {{ getInitials(user.name) }}
                            </AvatarFallback>
                        </Avatar>
                        <span class="sr-only">Open user menu</span>
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-56 rounded-lg">
                    <UserMenuContent :user="user" />
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </header>
</template>
