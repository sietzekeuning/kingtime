<script setup lang="ts">
import { computed } from 'vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { EnumOptions } from '@/lib/enums';

const props = defineProps<{
    label?: string;
    enumOptions: EnumOptions;
    disabled?: boolean;
}>();

const modelValue = defineModel<string | null>({ required: true });

const selectedOption = computed(() =>
    Object.values(props.enumOptions).find(
        (option) => option.value === modelValue.value,
    ),
);
</script>

<template>
    <Select v-model="modelValue" :disabled="disabled">
        <SelectTrigger class="w-full">
            <SelectValue v-if="selectedOption">
                <div class="flex items-center gap-2">
                    <span
                        v-if="selectedOption.colorClass"
                        class="size-3 shrink-0 rounded-full"
                        :class="selectedOption.colorClass"
                    />
                    <span>{{ selectedOption.label }}</span>
                </div>
            </SelectValue>
            <SelectValue
                v-else
                :placeholder="
                    label ? `Select ${label.toLowerCase()}` : 'Select'
                "
            />
        </SelectTrigger>
        <SelectContent>
            <SelectItem
                v-for="option in Object.values(enumOptions)"
                :key="option.value"
                :value="option.value"
            >
                <div class="flex items-center gap-2">
                    <span
                        v-if="option.colorClass"
                        class="size-3 rounded-full"
                        :class="option.colorClass"
                    />
                    <span>{{ option.label }}</span>
                </div>
            </SelectItem>
        </SelectContent>
    </Select>
</template>
