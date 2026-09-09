<script setup lang="ts" generic="TData = any">
import type { EnumOptions } from '@/lib/enums';

withDefaults(
    defineProps<{
        primary?: boolean;
        show?: string;
        label?: string;
        filterType?: 'text' | 'select';
        filterOptions?: EnumOptions;
        /**
         * For a select filter: names the view you get with no filter set.
         * Defaults to "All"; override it when the backend applies its own
         * default (e.g. the sollicitantenlijst, which shows only lopende
         * sollicitaties). For a text filter it is the input placeholder,
         * defaulting to "Search...", so a column that expects a date or an
         * amount can show what it accepts.
         */
        filterPlaceholder?: string;
    }>(),
    {
        filterType: 'text',
    },
);

defineSlots<{
    default?: (props: { item: TData }) => any;
}>();
</script>

<template>
    <!-- This component is config-only — DataTable parses its props/slot programmatically. -->
    <div :class="{ 'font-bold': primary }">
        <slot :item="{} as TData" />
    </div>
</template>
