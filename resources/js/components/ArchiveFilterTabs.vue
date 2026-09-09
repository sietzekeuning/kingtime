<script setup lang="ts">
import { computed } from 'vue';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';

/**
 * Segmented switch above a list with an `is_active` column: active rows
 * (the default, no filter), the archived ones, or both. The model is the
 * `filter[is_active]` value the table sends: `null`, `'0'` or `'all'`.
 */
const model = defineModel<string | null>({ required: true });

const tab = computed<string>({
    get: () => model.value ?? 'active',
    set: (value) => {
        model.value = value === 'active' ? null : value;
    },
});
</script>

<template>
    <Tabs v-model="tab">
        <TabsList>
            <TabsTrigger value="active">Active</TabsTrigger>
            <TabsTrigger value="0">Archived</TabsTrigger>
            <TabsTrigger value="all">All</TabsTrigger>
        </TabsList>
    </Tabs>
</template>
