<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Archive, ArchiveRestore } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';

/**
 * Archives or restores a project or client. Archiving hides the record from
 * the lists and selects but keeps every hour; the buttons in a list row are
 * icon only, the ones in a page header carry a label.
 */
const props = defineProps<{
    active: boolean;
    archiveUrl: string;
    restoreUrl: string;
    /** What is being archived, for the tooltip: "project" or "client". */
    subject: string;
    iconOnly?: boolean;
}>();

const busy = ref(false);

const title = computed(() =>
    props.active ? `Archive ${props.subject}` : `Restore ${props.subject}`,
);

function toggle(event: Event) {
    // A row button lives inside a clickable row; the click must not open it.
    event.stopPropagation();
    event.preventDefault();

    const options = {
        preserveScroll: true,
        onStart: () => (busy.value = true),
        onFinish: () => (busy.value = false),
    };

    if (props.active) {
        router.post(props.archiveUrl, {}, options);
    } else {
        router.delete(props.restoreUrl, options);
    }
}
</script>

<template>
    <button
        v-if="iconOnly"
        type="button"
        class="text-muted-foreground hover:text-foreground transition-colors disabled:opacity-50"
        :title="title"
        :disabled="busy"
        @click="toggle"
    >
        <ArchiveRestore v-if="!active" class="size-4" />
        <Archive v-else class="size-4" />
    </button>
    <Button
        v-else
        variant="outline"
        :title="title"
        :disabled="busy"
        @click="toggle"
    >
        <ArchiveRestore v-if="!active" class="size-4" />
        <Archive v-else class="size-4" />
        {{ active ? 'Archive' : 'Restore' }}
    </Button>
</template>
