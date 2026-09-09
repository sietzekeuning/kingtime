<script setup lang="ts">
import { AlignLeft, Lock } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { parseHoursInput } from '@/lib/time';
import { formatHours } from '@/lib/utils';
import type { TimesheetCellData } from '@/types/generated';

/**
 * One editable cell of the week grid. Typing hours and leaving the field
 * saves them; clearing the field removes the entry. A cell that holds
 * several entries, a locked entry or a running timer is read only and
 * opens the day view instead.
 */
const props = defineProps<{
    cell: TimesheetCellData;
    saving?: boolean;
}>();

const emit = defineEmits<{
    save: [hours: number | null];
    openDay: [date: string];
}>();

const editable = computed(
    () =>
        !props.cell.is_locked &&
        !props.cell.is_running &&
        props.cell.entries_count <= 1,
);

const hasHours = computed(() => Number.parseFloat(props.cell.hours) > 0);

const displayValue = () =>
    hasHours.value ? formatHours(props.cell.hours) : '';

const draft = ref(displayValue());
const invalid = ref(false);
const focused = ref(false);

watch(
    () => props.cell.hours,
    () => {
        if (!focused.value) {
            draft.value = displayValue();
        }
    },
);

function onFocus(event: FocusEvent) {
    focused.value = true;
    (event.target as HTMLInputElement).select();
}

function commit() {
    focused.value = false;

    const parsed = parseHoursInput(draft.value);

    if (parsed === undefined) {
        invalid.value = true;

        return;
    }

    invalid.value = false;

    const current = hasHours.value ? Number.parseFloat(props.cell.hours) : null;
    const next =
        parsed !== null && parsed > 0 ? Math.round(parsed * 100) / 100 : null;

    if (next === current) {
        draft.value = displayValue();

        return;
    }

    emit('save', next);
}

const readOnlyTitle = computed(() => {
    if (props.cell.is_running) {
        return 'Timer running · open the day view';
    }

    if (props.cell.is_locked) {
        return 'Billed or locked';
    }

    return `${props.cell.entries_count} entries · open the day view`;
});
</script>

<template>
    <div class="relative">
        <input
            v-if="editable"
            v-model="draft"
            type="text"
            inputmode="decimal"
            class="bg-background h-9 w-20 rounded-md border pr-2 pl-6 text-right text-sm tabular-nums transition-colors outline-none focus-visible:ring-[3px]"
            :class="[
                invalid
                    ? 'border-destructive focus-visible:ring-destructive/20'
                    : 'border-input focus-visible:border-ring focus-visible:ring-ring/50',
                saving ? 'opacity-50' : '',
                hasHours ? '' : 'text-muted-foreground',
            ]"
            :disabled="saving"
            :title="cell.notes ?? undefined"
            placeholder="0,00"
            @focus="onFocus"
            @blur="commit"
            @keydown.enter.prevent="($event.target as HTMLInputElement).blur()"
            @keydown.escape="
                draft = displayValue();
                ($event.target as HTMLInputElement).blur();
            "
        />
        <button
            v-else
            type="button"
            class="text-muted-foreground hover:bg-muted flex h-9 w-20 items-center justify-end gap-1 rounded-md pr-2 pl-6 text-right text-sm tabular-nums transition-colors"
            :title="readOnlyTitle"
            @click="emit('openDay', cell.date)"
        >
            <span
                v-if="cell.is_running"
                class="bg-primary size-1.5 shrink-0 animate-pulse rounded-full"
            />
            <Lock v-else-if="cell.is_locked" class="size-3 shrink-0" />
            {{ hasHours ? formatHours(cell.hours) : '·' }}
        </button>
        <AlignLeft
            v-if="cell.notes"
            class="text-muted-foreground pointer-events-none absolute top-1/2 left-1.5 size-3.5 -translate-y-1/2"
        />
    </div>
</template>
