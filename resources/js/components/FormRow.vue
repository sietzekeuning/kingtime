<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { computed, getCurrentInstance } from 'vue';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

const props = defineProps<{
    label?: string;
    field?: string;
    /** Multiple fields — show combined errors below the row (e.g. for grouped name fields). */
    fields?: string[];
    /** Hide error display — when FormRow only provides error context for the red border. */
    hideError?: boolean;
    required?: boolean;
    /** Inline two-column layout already from the mobile breakpoint (used in card sidebars). */
    inline?: boolean;
    /** Hide the label on mobile (paired with inline for tight checkbox/toggle rows). */
    hideLabelMobile?: boolean;
}>();

function findFormInParentTree(instance: any): InertiaForm<any> | undefined {
    if (!instance) {
        return undefined;
    }

    if (instance.props?.form) {
        return instance.props.form as InertiaForm<any>;
    }

    return findFormInParentTree(instance.parent);
}

const instance = getCurrentInstance();
const form = findFormInParentTree(instance);

const errorMessage = computed(() => {
    if (form && props.field) {
        return (form.errors as Record<string, string>)[props.field] ?? null;
    }

    return null;
});

const combinedErrorMessages = computed(() => {
    if (!form || !props.fields?.length) {
        return [];
    }

    const errors = form.errors as Record<string, string>;

    return props.fields.map((f) => errors[f]).filter(Boolean);
});

defineExpose({ errorMessage });
</script>

<template>
    <div
        :data-field="field"
        :class="
            cn(
                label
                    ? [
                          '-mx-2 -my-1 grid items-start rounded-md p-2 transition-colors has-focus-within:bg-blue-50/80',
                          inline && !hideLabelMobile
                              ? 'grid-cols-[100px_1fr] gap-2 md:grid-cols-[140px_1fr] md:gap-4 xl:grid-cols-[200px_1fr]'
                              : 'grid-cols-1 gap-4 md:grid-cols-[140px_1fr] xl:grid-cols-[200px_1fr]',
                      ]
                    : '',
            )
        "
    >
        <Label
            v-if="label"
            :for="$attrs.id as string"
            :class="
                cn('mt-[10px] text-sm', hideLabelMobile && 'hidden md:block')
            "
        >
            {{ label }}
            <span v-if="required" class="ml-1 text-red-500">*</span>
        </Label>
        <div>
            <slot />
            <template v-if="!hideError">
                <template v-if="fields?.length && combinedErrorMessages.length">
                    <div class="mt-2 space-y-1">
                        <InputError
                            v-for="(msg, i) in combinedErrorMessages"
                            :key="i"
                            :message="msg"
                        />
                    </div>
                </template>
                <InputError
                    v-else-if="errorMessage && field"
                    class="mt-2"
                    :message="errorMessage"
                />
            </template>
        </div>
    </div>
</template>
