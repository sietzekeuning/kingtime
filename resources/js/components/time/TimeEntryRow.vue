<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Lock, Pencil, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import TimerButton from '@/components/time/TimerButton.vue';
import { Button } from '@/components/ui/button';
import { useConfirmDelete } from '@/composables/useConfirmDelete';
import { useTimerElapsed } from '@/composables/useTimerElapsed';
import { formatHours } from '@/lib/utils';
import timeEntries from '@/routes/time-entries';
import type { TimeEntryData } from '@/types/generated';

const props = defineProps<{
    entry: TimeEntryData;
    /** Only the owner may edit, delete or time an entry. */
    canEdit: boolean;
}>();

const locked = computed(() => props.entry.is_locked || props.entry.is_billed);

const { formatted: elapsed } = useTimerElapsed(() =>
    props.entry.is_running ? props.entry.timer_started_at : null,
);

const { confirmDelete } = useConfirmDelete();

function destroy(event: Event) {
    void confirmDelete(timeEntries.destroy(props.entry.id!).url, {
        event,
        title: 'Delete this time entry?',
        description: `${formatHours(props.entry.hours)} hours on ${props.entry.project_name}. This cannot be undone.`,
    });
}
</script>

<template>
    <div class="flex items-center gap-3 py-3">
        <span
            class="size-2.5 shrink-0 rounded-full"
            :style="{
                backgroundColor: entry.project_color ?? 'var(--primary)',
            }"
        />
        <div class="min-w-0 flex-1">
            <div class="flex items-baseline gap-2 text-sm">
                <span class="truncate font-medium">{{
                    entry.project_name
                }}</span>
                <span
                    v-if="entry.client_name"
                    class="text-muted-foreground truncate"
                >
                    · {{ entry.client_name }}
                </span>
            </div>
            <p
                v-if="entry.notes"
                class="text-muted-foreground truncate text-sm"
            >
                {{ entry.notes }}
            </p>
        </div>
        <div class="flex shrink-0 items-center gap-3">
            <span
                v-if="entry.is_running"
                class="text-primary inline-flex items-center gap-1.5 font-mono text-xs"
            >
                <span class="bg-primary size-1.5 animate-pulse rounded-full" />
                {{ elapsed }}
            </span>
            <span
                v-else-if="locked"
                class="text-muted-foreground inline-flex items-center gap-1 text-xs"
                :title="entry.is_billed ? 'Billed' : 'Locked'"
            >
                <Lock class="size-3" />
            </span>
            <span class="w-14 text-right font-semibold tabular-nums">
                {{ formatHours(entry.hours) }}
            </span>
            <div v-if="canEdit" class="flex items-center gap-1">
                <TimerButton :entry="entry" :disabled="locked" />
                <Button
                    variant="ghost"
                    size="icon-sm"
                    as-child
                    title="Edit entry"
                >
                    <Link :href="timeEntries.edit(entry.id!)">
                        <Pencil class="size-3.5" />
                    </Link>
                </Button>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    class="text-muted-foreground hover:text-destructive"
                    title="Delete entry"
                    :disabled="locked"
                    @click="destroy"
                >
                    <Trash2 class="size-3.5" />
                </Button>
            </div>
        </div>
    </div>
</template>
