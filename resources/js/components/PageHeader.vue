<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import FaIcon from '@/components/FaIcon.vue';

/**
 * Title bar of a page: a full-width row under the app header with an
 * optional back-link, the title, an optional subtitle and an #actions slot
 * for the primary button(s) on the right. It pulls itself into the page
 * padding (`p-4 md:p-6`) so its bottom border runs edge to edge.
 */
defineProps<{
    title: string;
    subtitle?: string;
    /** Renders a muted arrow-left link above the title. */
    backHref?: InertiaLinkProps['href'];
    backLabel?: string;
}>();
</script>

<template>
    <div
        class="border-border -mx-4 -mt-4 flex min-h-16 items-center justify-between gap-4 border-b px-4 py-3 md:-mx-6 md:-mt-6 md:px-6"
    >
        <div class="min-w-0">
            <Link
                v-if="backHref"
                :href="backHref"
                class="text-muted-foreground hover:text-foreground mb-0.5 inline-flex items-center gap-1.5 text-xs"
            >
                <FaIcon icon="arrow-left" class="text-[11px]" />
                {{ backLabel ?? 'Back' }}
            </Link>
            <h1 class="truncate text-lg font-semibold tracking-tight">
                {{ title }}
            </h1>
            <p v-if="subtitle" class="text-muted-foreground truncate text-sm">
                {{ subtitle }}
            </p>
        </div>
        <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
