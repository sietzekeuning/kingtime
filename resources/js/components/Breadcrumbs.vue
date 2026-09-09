<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import FaIcon from '@/components/FaIcon.vue';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

/**
 * Breadcrumb trail in the app header: parents as quiet links, chevrons in
 * between, the current page in the foreground color.
 */
type Props = {
    breadcrumbs: BreadcrumbItemType[];
};

defineProps<Props>();
</script>

<template>
    <nav aria-label="Breadcrumb" class="min-w-0">
        <ol class="flex min-w-0 items-center gap-1.5 text-sm">
            <template v-for="(item, index) in breadcrumbs" :key="index">
                <li class="min-w-0">
                    <span
                        v-if="index === breadcrumbs.length - 1"
                        class="text-foreground block truncate font-medium"
                        aria-current="page"
                    >
                        {{ item.title }}
                    </span>
                    <Link
                        v-else
                        :href="item.href"
                        class="text-muted-foreground hover:text-foreground block truncate transition-colors"
                    >
                        {{ item.title }}
                    </Link>
                </li>
                <li
                    v-if="index !== breadcrumbs.length - 1"
                    class="text-muted-foreground/60 shrink-0"
                    aria-hidden="true"
                >
                    <FaIcon icon="chevron-right" class="text-[11px]" />
                </li>
            </template>
        </ol>
    </nav>
</template>
