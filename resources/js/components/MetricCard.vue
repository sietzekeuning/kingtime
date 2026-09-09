<script setup lang="ts">
import { computed } from 'vue';
import { ArrowDown, ArrowUp, Minus } from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { Card } from '@/components/ui/card';

/**
 * Reusable headline metric tile. Used on the reports dashboard, day-close
 * preview, and any future report. The `delta` reads as % change vs the
 * previous period; the icon next to it flips on sign.
 */
const props = defineProps<{
    label: string;
    value: string;
    /** Optional `+12.4` style number (already computed by the caller). */
    deltaPercent?: number | null;
    /** Optional sub-label below the value, e.g. "42 orders". */
    hint?: string;
    /** Optional icon; renders top-right. */
    icon?: LucideIcon;
    /** Optional extra classes on the value, e.g. to colour a bad number red. */
    valueClass?: string;
}>();

const deltaClass = computed(() => {
    if (props.deltaPercent === null || props.deltaPercent === undefined) {
        return '';
    }

    if (props.deltaPercent > 0) {
        return 'text-emerald-700';
    }

    if (props.deltaPercent < 0) {
        return 'text-rose-700';
    }

    return 'text-muted-foreground';
});

const deltaIcon = computed(() => {
    if (props.deltaPercent === null || props.deltaPercent === undefined) {
        return null;
    }

    if (props.deltaPercent > 0) {
        return ArrowUp;
    }

    if (props.deltaPercent < 0) {
        return ArrowDown;
    }

    return Minus;
});

const deltaText = computed(() => {
    if (props.deltaPercent === null || props.deltaPercent === undefined) {
        return '';
    }

    const formatted = Math.abs(props.deltaPercent).toFixed(1);

    return `${formatted}%`;
});
</script>

<template>
    <Card class="gap-0 p-4">
        <div class="flex items-start justify-between gap-2">
            <div class="text-muted-foreground text-xs tracking-wide uppercase">
                {{ label }}
            </div>
            <component
                :is="icon"
                v-if="icon"
                class="text-muted-foreground size-4"
            />
        </div>
        <div
            class="mt-1 text-3xl font-semibold tabular-nums"
            :class="valueClass"
        >
            {{ value }}
        </div>
        <div class="mt-1 flex items-baseline gap-2 text-xs">
            <span
                v-if="deltaIcon"
                class="inline-flex items-center gap-0.5 font-medium"
                :class="deltaClass"
            >
                <component :is="deltaIcon" class="size-2.5" />
                {{ deltaText }}
            </span>
            <span v-if="hint" class="text-muted-foreground">{{ hint }}</span>
        </div>
    </Card>
</template>
