<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import FaIcon from '@/components/FaIcon.vue';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

withDefaults(
    defineProps<{
        items: NavItem[];
        label?: string;
    }>(),
    { label: 'General' },
);

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <SidebarGroup class="px-3 py-0 group-data-[collapsible=icon]:px-2">
        <SidebarGroupLabel
            class="text-muted-foreground/80 h-7 px-2.5 text-[13px] font-normal"
        >
            {{ label }}
        </SidebarGroupLabel>
        <SidebarMenu class="gap-0.5">
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    class="h-9 gap-2.5 rounded-lg px-2.5 text-[15px] data-[active=true]:font-medium"
                    :is-active="isCurrentOrParentUrl(item.href)"
                    :tooltip="item.title"
                >
                    <Link :href="item.href">
                        <FaIcon
                            :icon="item.icon"
                            class="text-muted-foreground text-[15px]"
                        />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
                <SidebarMenuBadge v-if="item.badge">
                    {{ item.badge }}
                </SidebarMenuBadge>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
