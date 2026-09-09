<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Play, Square } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import timeEntries from '@/routes/time-entries';
import type { TimeEntryData } from '@/types/generated';

/**
 * Start/stop toggle for one entry. Starting resumes the entry's timer and
 * stops whichever other timer the user had running.
 */
const props = defineProps<{
    entry: TimeEntryData;
    disabled?: boolean;
}>();

const processing = ref(false);

function toggle(event: Event) {
    event.stopPropagation();

    const route = props.entry.is_running
        ? timeEntries.stop(props.entry.id!)
        : timeEntries.start(props.entry.id!);

    router.post(
        route.url,
        {},
        {
            preserveScroll: true,
            onStart: () => (processing.value = true),
            onFinish: () => (processing.value = false),
        },
    );
}
</script>

<template>
    <Button
        :variant="entry.is_running ? 'default' : 'outline'"
        size="icon-sm"
        :title="entry.is_running ? 'Stop timer' : 'Start timer'"
        :disabled="disabled || processing"
        @click="toggle"
    >
        <Square v-if="entry.is_running" class="size-3.5 fill-current" />
        <Play v-else class="size-3.5" />
    </Button>
</template>
