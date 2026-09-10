<script setup lang="ts">
import { computed } from 'vue';
import FaIcon from '@/components/FaIcon.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAppearance } from '@/composables/useAppearance';
import type { Appearance } from '@/composables/useAppearance';

/**
 * Light, dark or system, as a small icon button with a menu. The icon shows
 * the current choice: a sun, a moon, or a half-filled circle for "follow
 * the system". Shares its state with Settings > Appearance.
 */
const { appearance, updateAppearance } = useAppearance();

const options: { value: Appearance; label: string; icon: string }[] = [
    { value: 'light', label: 'Light', icon: 'sun' },
    { value: 'dark', label: 'Dark', icon: 'moon' },
    { value: 'system', label: 'System', icon: 'circle-half-stroke' },
];

const current = computed(
    () =>
        options.find((option) => option.value === appearance.value) ??
        options[2],
);
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger :as-child="true">
            <Button
                variant="ghost"
                size="icon"
                class="text-muted-foreground hover:text-foreground rounded-full"
                :title="`Appearance: ${current.label}`"
                aria-label="Appearance"
            >
                <FaIcon :icon="current.icon" class="text-base" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-40">
            <DropdownMenuItem
                v-for="option in options"
                :key="option.value"
                class="cursor-pointer gap-2.5"
                @select="updateAppearance(option.value)"
            >
                <FaIcon :icon="option.icon" class="text-sm" />
                <span class="flex-1">{{ option.label }}</span>
                <FaIcon
                    v-if="option.value === appearance"
                    icon="check"
                    class="text-primary text-xs"
                />
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
