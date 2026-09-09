<script setup lang="ts">
import type { LucideIcon } from '@lucide/vue';

/**
 * Empty/"geen data" placeholder in two flavors:
 * - `dashed` (default): the inline dashed-border box used inside cards and
 *   detail sections.
 * - `centered`: the tall centered block with an optional icon bubble, used
 *   when a whole page or panel is empty. Put a CTA in the #action slot.
 */
withDefaults(
    defineProps<{
        variant?: 'dashed' | 'centered';
        icon?: LucideIcon;
        title?: string;
        description?: string;
    }>(),
    { variant: 'dashed' },
);
</script>

<template>
    <div
        v-if="variant === 'dashed'"
        class="border-border text-muted-foreground rounded-md border border-dashed p-4 text-sm"
    >
        <slot>{{ description ?? title }}</slot>
    </div>
    <div
        v-else
        class="flex flex-col items-center justify-center gap-3 py-12 text-center"
    >
        <div
            v-if="icon"
            class="bg-muted text-muted-foreground flex size-12 items-center justify-center rounded-full"
        >
            <component :is="icon" class="size-5" />
        </div>
        <h2 v-if="title" class="text-lg font-medium">{{ title }}</h2>
        <p class="text-muted-foreground max-w-md text-sm">
            <slot>{{ description }}</slot>
        </p>
        <slot name="action" />
    </div>
</template>
