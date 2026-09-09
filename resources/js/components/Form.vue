<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = withDefaults(
    defineProps<{
        form: InertiaForm<any>;
        id?: string;
    }>(),
    { id: undefined },
);

defineEmits<{
    submit: [];
}>();

watch(
    () => JSON.stringify(props.form.errors),
    (errorsJson) => {
        if (errorsJson === '{}') {
            return;
        }

        const errors = JSON.parse(errorsJson);
        const firstErrorKey = Object.keys(errors)[0];

        if (!firstErrorKey) {
            return;
        }

        setTimeout(() => {
            const row = document.querySelector(
                `[data-field="${firstErrorKey}"]`,
            );
            const input = row?.querySelector(
                'input, select, textarea',
            ) as HTMLElement | null;

            if (input) {
                input.focus({ preventScroll: true });
                input.scrollIntoView({ behavior: 'smooth', block: 'center' });

                return;
            }

            const errorEl = Array.from(
                document.querySelectorAll('.text-red-600'),
            ).find((el) => (el as HTMLElement).checkVisibility());
            errorEl?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 100);
    },
);
</script>

<template>
    <form :id="id" @submit.prevent="$emit('submit')">
        <slot />
    </form>
</template>
