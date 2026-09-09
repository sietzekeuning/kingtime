<script setup lang="ts">
import FormRow from '@/components/FormRow.vue';
import { Label } from '@/components/ui/label';

/**
 * One field of the time entry form. `stacked` puts the label above the
 * input (the compact card on the timesheet); otherwise it is the standard
 * two-column FormRow used on the edit page.
 */
defineProps<{
    label: string;
    field: string;
    required?: boolean;
    stacked?: boolean;
    inline?: boolean;
}>();
</script>

<template>
    <div v-if="stacked" class="space-y-1.5">
        <Label class="text-sm">
            {{ label }}
            <span v-if="required" class="text-red-500">*</span>
        </Label>
        <FormRow :field="field">
            <slot />
        </FormRow>
    </div>
    <FormRow
        v-else
        :label="label"
        :field="field"
        :required="required"
        :inline="inline"
    >
        <slot />
    </FormRow>
</template>
