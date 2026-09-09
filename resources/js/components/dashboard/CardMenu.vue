<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import FaIcon from '@/components/FaIcon.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

/**
 * The "…" button in the corner of a dashboard card: a small menu of links
 * to the pages behind the card.
 */
defineProps<{
    items: {
        title: string;
        href: NonNullable<InertiaLinkProps['href']>;
        icon: string;
    }[];
}>();
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                variant="outline"
                size="icon-sm"
                class="text-muted-foreground rounded-lg"
                title="More"
            >
                <FaIcon icon="ellipsis" class="text-sm" />
                <span class="sr-only">More</span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-48 rounded-lg">
            <DropdownMenuItem v-for="item in items" :key="item.title" as-child>
                <Link :href="item.href" class="cursor-pointer">
                    <FaIcon
                        :icon="item.icon"
                        class="text-muted-foreground text-sm"
                    />
                    {{ item.title }}
                </Link>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
