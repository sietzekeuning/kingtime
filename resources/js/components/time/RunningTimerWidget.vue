<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Square } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { useTimerElapsed } from '@/composables/useTimerElapsed';
import timeEntries from '@/routes/time-entries';
import type { TimeEntryData } from '@/types/generated';

/**
 * The user's running timer, shared on every page through the `runningTimer`
 * prop. Ticks client-side from `timer_started_at`; the stop button posts to
 * the stop route and returns to the current page.
 */
const page = usePage();

const timer = computed(
    () => (page.props.runningTimer as TimeEntryData | null | undefined) ?? null,
);

const { formatted: elapsed } = useTimerElapsed(
    () => timer.value?.timer_started_at,
);

const stopping = ref(false);

function stop() {
    if (!timer.value) {
        return;
    }

    router.post(
        timeEntries.stop(timer.value.id!).url,
        {},
        {
            preserveScroll: true,
            onStart: () => (stopping.value = true),
            onFinish: () => (stopping.value = false),
        },
    );
}
</script>

<template>
    <div class="ml-auto flex items-center">
        <div
            v-if="timer"
            class="border-primary/30 bg-primary/5 flex items-center gap-2 rounded-full border py-1 pr-1 pl-3"
        >
            <span class="bg-primary size-2 animate-pulse rounded-full" />
            <Link
                :href="timeEntries.index({ query: { date: timer.spent_on } })"
                class="hidden max-w-48 truncate text-sm font-medium hover:underline sm:inline"
                :title="timer.project_name ?? ''"
            >
                {{ timer.project_name }}
            </Link>
            <span class="font-mono text-sm tabular-nums">{{ elapsed }}</span>
            <Button
                size="icon-sm"
                class="size-7 rounded-full"
                title="Stop timer"
                :disabled="stopping"
                @click="stop"
            >
                <Square class="size-3 fill-current" />
            </Button>
        </div>
    </div>
</template>
