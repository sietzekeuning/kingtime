<script setup lang="ts">
import { findIconDefinition } from '@fortawesome/fontawesome-svg-core';
import type {
    IconDefinition,
    IconName,
    IconPrefix,
} from '@fortawesome/fontawesome-svg-core';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';

type Weight = 'light' | 'regular' | 'solid' | 'brands';

/**
 * Font Awesome Pro icon by name: `<FaIcon icon="clock" />`. Looks the name
 * up in the library filled by `resources/js/plugins/fontawesome.ts`, so a
 * name that is not registered there renders nothing. The icon scales with
 * the font size of its parent (1em), so size it with `text-sm`, `text-lg`
 * and friends rather than `size-*`.
 */
const props = withDefaults(
    defineProps<{
        icon: string;
        weight?: Weight;
        /** Reserve the same width for every glyph, as in menus and lists. */
        fixedWidth?: boolean;
        class?: HTMLAttributes['class'];
    }>(),
    { weight: 'regular', fixedWidth: true },
);

const prefixesByWeight: Record<Weight, IconPrefix[]> = {
    light: ['fal', 'far', 'fas'],
    regular: ['far', 'fal', 'fas'],
    solid: ['fas', 'far', 'fal'],
    brands: ['fab'],
};

const definition = computed<IconDefinition | null>(() => {
    const iconName = props.icon.replace(/^fa-/, '') as IconName;

    for (const prefix of [...prefixesByWeight[props.weight], 'fab' as const]) {
        const found = findIconDefinition({ prefix, iconName });

        if (found) {
            return found;
        }
    }

    return null;
});
</script>

<template>
    <FontAwesomeIcon
        v-if="definition"
        :icon="definition"
        :fixed-width="fixedWidth"
        :class="props.class"
        aria-hidden="true"
    />
</template>
