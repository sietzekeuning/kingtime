<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';

/**
 * Standard admin page header: optional back-link, title, optional subtitle,
 * and an #actions slot for the primary button(s) on the right. Replaces the
 * hand-rolled "flex items-center justify-between + h1" block on list and
 * detail pages.
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
    <div class="flex items-center justify-between gap-4">
        <div class="space-y-0.5">
            <Link
                v-if="backHref"
                :href="backHref"
                class="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-sm"
            >
                <ArrowLeft class="size-3.5" />
                {{ backLabel ?? 'Back' }}
            </Link>
            <h1 class="text-2xl font-semibold tracking-tight">{{ title }}</h1>
            <p v-if="subtitle" class="text-muted-foreground text-sm">
                {{ subtitle }}
            </p>
        </div>
        <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
