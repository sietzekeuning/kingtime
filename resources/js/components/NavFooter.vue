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

/** Outside links (GitHub, docs) open in a new tab; our own pages do not. */
function isExternal(item: NavItem): boolean {
    return toUrl(item.href).startsWith('http');
}
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
                            :target="isExternal(item) ? '_blank' : undefined"
                            :rel="
                                isExternal(item)
                                    ? 'noopener noreferrer'
                                    : undefined
                            "
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
