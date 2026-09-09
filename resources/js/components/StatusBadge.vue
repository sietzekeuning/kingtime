<script setup lang="ts">
import type { LucideIcon } from '@lucide/vue';

export type StatusBadgeTone =
    | 'green'
    | 'emerald'
    | 'red'
    | 'rose'
    | 'amber'
    | 'yellow'
    | 'orange'
    | 'blue'
    | 'sky'
    | 'indigo'
    | 'purple'
    | 'gray'
    | 'slate';

defineProps<{
    tone: StatusBadgeTone;
    /** Optional leading icon. */
    icon?: LucideIcon;
    /** Label text; a default slot overrides it. */
    label?: string;
}>();

/**
 * Statically enumerated so Tailwind's content scanner sees every class —
 * a computed template string would silently produce an unstyled pill.
 */
const toneClasses: Record<StatusBadgeTone, string> = {
    green: 'bg-green-100 text-green-700',
    emerald: 'bg-emerald-100 text-emerald-700',
    red: 'bg-red-100 text-red-700',
    rose: 'bg-rose-100 text-rose-700',
    amber: 'bg-amber-100 text-amber-700',
    yellow: 'bg-yellow-100 text-yellow-700',
    orange: 'bg-orange-100 text-orange-700',
    blue: 'bg-blue-100 text-blue-700',
    sky: 'bg-sky-100 text-sky-700',
    indigo: 'bg-indigo-100 text-indigo-700',
    purple: 'bg-purple-100 text-purple-700',
    gray: 'bg-gray-100 text-gray-700',
    slate: 'bg-slate-100 text-slate-700',
};
</script>

<template>
    <span
        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
        :class="toneClasses[tone]"
    >
        <component :is="icon" v-if="icon" class="size-3" />
        <slot>{{ label }}</slot>
    </span>
</template>
