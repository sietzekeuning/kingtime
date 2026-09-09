<script setup lang="ts">
import FaIcon from '@/components/FaIcon.vue';
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

type Props = {
    items: NavItem[];
    class?: string;
};

defineProps<Props>();
</script>

<template>
    <SidebarGroup
        :class="`p-0 group-data-[collapsible=icon]:p-0 ${$props.class || ''}`"
    >
        <SidebarGroupContent>
            <SidebarMenu class="gap-0.5">
                <SidebarMenuItem v-for="item in items" :key="item.title">
                    <SidebarMenuButton
                        class="text-muted-foreground hover:text-foreground h-8 gap-2.5 rounded-lg px-2.5 text-sm"
                        as-child
                        :tooltip="item.title"
                    >
                        <a
                            :href="toUrl(item.href)"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <FaIcon :icon="item.icon" class="text-sm" />
                            <span>{{ item.title }}</span>
                        </a>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroupContent>
    </SidebarGroup>
</template>
