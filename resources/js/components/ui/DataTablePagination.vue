<script setup lang="ts" generic="TData">
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { rememberListUrl } from '@/lib/listReturn';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { PaginatedData } from '@/interfaces/PaginatedData';

const props = defineProps<{
    data: PaginatedData<TData>;
}>();

const maxPage = computed(() => Math.max(1, Math.ceil(props.data.total / props.data.per_page)));

function setPageSize(value: unknown) {
    if (value === null || value === undefined) return;
    const url = new URL(window.location.href);
    url.searchParams.set('perPage', String(value));
    url.searchParams.set('page', '1');
    const target = `${url.pathname}?${url.searchParams.toString()}`;
    rememberListUrl(target);
    router.visit(target, {
        preserveState: true,
        preserveScroll: true,
    });
}

function setPageIndex(pageNumber: number) {
    const url = new URL(window.location.href);
    url.searchParams.set('page', String(Math.max(1, pageNumber)));
    const target = `${url.pathname}?${url.searchParams.toString()}`;
    rememberListUrl(target);
    router.visit(target, {
        preserveState: true,
        preserveScroll: true,
    });
}

function previousPage() {
    if (props.data.prev_page_url) {
        rememberListUrl(props.data.prev_page_url);
        router.visit(props.data.prev_page_url, {
            preserveState: true,
            preserveScroll: true,
        });
    }
}

function nextPage() {
    if (props.data.next_page_url) {
        rememberListUrl(props.data.next_page_url);
        router.visit(props.data.next_page_url, {
            preserveState: true,
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <div
        class="flex flex-col gap-3 border-t border-sidebar-border/70 px-3 py-2 text-sm md:flex-row md:items-center md:justify-between"
    >
        <div class="text-muted-foreground">
            <template v-if="data.total > 0">
                Rows {{ data.from }} to {{ data.to }} of {{ data.total }}
            </template>
            <template v-else>Geen rijen</template>
        </div>
        <div class="flex flex-wrap items-center gap-4 md:gap-6">
            <div class="flex items-center gap-2">
                <span class="text-muted-foreground text-xs">Rows per page</span>
                <Select :model-value="String(data.per_page)" @update:model-value="setPageSize">
                    <SelectTrigger class="h-8 w-[80px]">
                        <SelectValue :placeholder="`${data.per_page}`" />
                    </SelectTrigger>
                    <SelectContent side="top">
                        <SelectItem
                            v-for="size in [10, 25, 50, 100]"
                            :key="size"
                            :value="String(size)"
                        >
                            {{ size }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="text-muted-foreground tabular-nums">
                Page {{ data.current_page }} of {{ maxPage }}
            </div>
            <div class="flex items-center gap-1">
                <Button
                    variant="outline"
                    size="icon"
                    class="size-8"
                    :disabled="data.current_page === 1"
                    @click="setPageIndex(1)"
                >
                    <ChevronsLeft class="size-4" />
                </Button>
                <Button
                    variant="outline"
                    size="icon"
                    class="size-8"
                    :disabled="data.current_page === 1"
                    @click="previousPage"
                >
                    <ChevronLeft class="size-4" />
                </Button>
                <Button
                    variant="outline"
                    size="icon"
                    class="size-8"
                    :disabled="data.current_page >= maxPage"
                    @click="nextPage"
                >
                    <ChevronRight class="size-4" />
                </Button>
                <Button
                    variant="outline"
                    size="icon"
                    class="size-8"
                    :disabled="data.current_page >= maxPage"
                    @click="setPageIndex(maxPage)"
                >
                    <ChevronsRight class="size-4" />
                </Button>
            </div>
        </div>
    </div>
</template>
