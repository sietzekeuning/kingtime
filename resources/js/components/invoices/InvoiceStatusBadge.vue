<script setup lang="ts">
import StatusBadge from '@/components/StatusBadge.vue';
import type { StatusBadgeTone } from '@/components/StatusBadge.vue';
import { InvoiceStatus, InvoiceStatusOptions } from '@/lib/enums';

/**
 * Status pill for an invoice: maps the enum onto StatusBadge's tones so it
 * looks like every other status pill in the app.
 */
defineProps<{
    status: InvoiceStatus;
}>();

const tones: Record<InvoiceStatus, StatusBadgeTone> = {
    [InvoiceStatus.Draft]: 'slate',
    [InvoiceStatus.Open]: 'blue',
    [InvoiceStatus.Paid]: 'emerald',
    [InvoiceStatus.Late]: 'rose',
    [InvoiceStatus.Uncollectible]: 'gray',
};

const label = (status: InvoiceStatus) =>
    Object.values(InvoiceStatusOptions).find(
        (option) => option.value === status,
    )?.label ?? status;
</script>

<template>
    <StatusBadge :tone="tones[status]" :label="label(status)" />
</template>
