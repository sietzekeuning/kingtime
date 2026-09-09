<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { Button } from '@/components/ui/button';

/**
 * Previous / next arrows, a "back to now" button and the label of the
 * period on show. The right side is a slot for the period's total.
 */
defineProps<{
    label: string;
    /** "Today", "This week" or "This month". */
    currentLabel: string;
    previousTitle: string;
    nextTitle: string;
}>();

const emit = defineEmits<{
    previous: [];
    next: [];
    current: [];
}>();
</script>

<template>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex items-center gap-1">
            <Button
                variant="outline"
                size="icon-sm"
                :title="previousTitle"
                @click="emit('previous')"
            >
                <ChevronLeft class="size-4" />
            </Button>
            <Button
                variant="outline"
                size="icon-sm"
                :title="nextTitle"
                @click="emit('next')"
            >
                <ChevronRight class="size-4" />
            </Button>
            <Button variant="outline" size="sm" @click="emit('current')">
                {{ currentLabel }}
            </Button>
            <span class="text-muted-foreground ml-2 text-sm">
                {{ label }}
            </span>
        </div>
        <slot />
    </div>
</template>
