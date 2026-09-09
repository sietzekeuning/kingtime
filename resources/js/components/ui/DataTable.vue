<script setup lang="ts" generic="TData, TValue">
import { ArrowDown, ArrowUp, GripVertical, X } from '@lucide/vue';
import { router } from '@inertiajs/vue3';
import {
    type ColumnDef,
    type ColumnFiltersState,
    type ColumnMeta,
    FlexRender,
    getCoreRowModel,
    getFilteredRowModel,
    type Row,
    useVueTable,
} from '@tanstack/vue-table';
import debounce from 'lodash/debounce';
import {
    computed,
    getCurrentInstance,
    h,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { PaginatedData } from '@/interfaces/PaginatedData';
import type { TableFilterApi } from '@/interfaces/TableFilterApi';
import type { EnumOption, EnumOptions } from '@/lib/enums';
import { rememberListUrl } from '@/lib/listReturn';
import DataTablePagination from './DataTablePagination.vue';

interface CustomColumnMeta extends ColumnMeta<any, unknown> {
    sortable?: boolean;
    filterable?: boolean;
    filterType?: 'text' | 'select';
    filterOptions?: EnumOptions;
    filterPlaceholder?: string;
}

type Url = string | { url: string } | null;


const props = withDefaults(
    defineProps<{
        data: PaginatedData<TData>;
        rowUrl?: (row: TData) => Url;
        rowClass?: (row: TData) => string | undefined;
        label?: string;
        /**
         * When true, renders a checkbox column as the first column with a
         * select-all checkbox in the header. Selected ids are exposed via
         * `v-model:selected`. The `#bulk-actions` slot is shown next to
         * `#buttons` whenever `selected.length > 0`.
         */
        selectable?: boolean;
        /**
         * Maps a row to its stable id used for selection state. Defaults to
         * `row.id`, which covers any model that extends BaseData with a `id`
         * UUID. Override if the row payload uses a different key.
         */
        rowId?: (row: TData) => string;
        /**
         * Adds a grip column and lets rows be dragged into a new order. The
         * table only knows the order it was handed, so dragging is disabled
         * while a filter or a column sort is active: in those views the
         * position on screen is not the position that would be saved. Listen
         * for `@reorder` to persist the ids.
         */
        reorderable?: boolean;
    }>(),
    {
        selectable: false,
        reorderable: false,
        rowId: (row: any) => String(row.id),
    },
);

const selected = defineModel<string[]>('selected', { default: () => [] });

function resolveUrl(value: Url): string | null {
    if (value == null) return null;
    return typeof value === 'string' ? value : value.url;
}

const emit = defineEmits<{
    (e: 'rowClick', row: TData): void;
    (e: 'reorder', orderedIds: string[]): void;
}>();

defineSlots<{
    rows?: any;
    buttons?: (props: { filters: TableFilterApi }) => any;
    'bulk-actions'?: (props: { selected: string[]; clear: () => void }) => any;
    empty?: any;
}>();

const visibleIds = computed(() => props.data.data.map((row) => props.rowId(row)));

const allVisibleSelected = computed(
    () => visibleIds.value.length > 0 && visibleIds.value.every((id) => selected.value.includes(id)),
);
const someVisibleSelected = computed(
    () => visibleIds.value.some((id) => selected.value.includes(id)) && !allVisibleSelected.value,
);
const headerCheckboxState = computed<boolean | 'indeterminate'>(() => {
    if (allVisibleSelected.value) return true;
    if (someVisibleSelected.value) return 'indeterminate';
    return false;
});

function toggleAllVisible(checked: boolean | 'indeterminate'): void {
    // Reka's CheckboxRoot fires `update:modelValue` with the *new* value.
    // We treat both `true` and `indeterminate` as "select all visible" since
    // an admin clicking an indeterminate header is asking to fill it in,
    // and a true→false toggle clears the visible subset.
    if (checked === true || checked === 'indeterminate') {
        selected.value = Array.from(new Set([...selected.value, ...visibleIds.value]));
    } else {
        const visible = new Set(visibleIds.value);
        selected.value = selected.value.filter((id) => !visible.has(id));
    }
}

function toggleRow(id: string, checked: boolean | 'indeterminate'): void {
    if (checked === true) {
        if (!selected.value.includes(id)) selected.value = [...selected.value, id];
    } else {
        selected.value = selected.value.filter((existing) => existing !== id);
    }
}

function clearSelection(): void {
    selected.value = [];
}

const columnFilters = ref<ColumnFiltersState>([]);
const sorting = ref<{ id: string; desc: boolean }[]>([]);

const instance = getCurrentInstance();
const rowsSlot =
    instance?.slots.rows && typeof instance.slots.rows === 'function'
        ? instance.slots.rows()
        : undefined;

const hasRowClickListener = computed(() => {
    const vnodeProps = instance?.vnode.props as
        | Record<string, unknown>
        | null
        | undefined;
    return Boolean(vnodeProps?.onRowClick || vnodeProps?.['onRow-click']);
});

const rowIsClickable = computed(
    () => Boolean(props.rowUrl) || hasRowClickListener.value,
);

const columns: ColumnDef<TData>[] = rowsSlot
    ? rowsSlot
          .filter((col) => col.props?.show)
          .map((col) => {
              const columnProps = col.props || {};
              const filterType =
                  columnProps.filterType ?? columnProps['filter-type'] ?? 'text';
              let filterOptions =
                  columnProps.filterOptions ?? columnProps['filter-options'];
              if (typeof filterOptions === 'function') {
                  filterOptions = filterOptions();
              }

              const filterPlaceholder =
                  columnProps.filterPlaceholder ??
                  columnProps['filter-placeholder'] ??
                  (filterType === 'select' ? 'All' : 'Search...');

              const showKey = columnProps.show as string;

              return {
                  id: showKey,
                  accessorKey: showKey,
                  header: columnProps.label,
                  meta: {
                      sortable: showKey && props.data.allowed_sorts.includes(showKey),
                      filterable:
                          showKey &&
                          columnProps.filterable !== false &&
                          props.data.allowed_filters.some((f) =>
                              typeof f === 'string' ? f === showKey : f.name === showKey,
                          ),
                      filterType,
                      filterOptions,
                      filterPlaceholder,
                  } as CustomColumnMeta,
                  cell: ({ row }: { row: Row<TData> }) => {
                      if (
                          col.children &&
                          typeof col.children === 'object' &&
                          'default' in col.children
                      ) {
                          const slot = col.children.default as (props: { item: TData }) => any;
                          return h('div', {}, slot({ item: row.original }));
                      }
                      return h('div', {}, row.getValue(showKey) as any);
                  },
              };
          })
    : [];

// A drag reorders the rows on screen straight away; the server round-trip that
// follows replaces the page and clears the override.
const optimisticOrder = ref<string[] | null>(null);
watch(
    () => props.data,
    () => {
        optimisticOrder.value = null;
    },
);

const orderedRows = computed<TData[]>(() => {
    const order = optimisticOrder.value;

    if (!order) {
        return props.data.data;
    }

    const position = new Map(order.map((id, index) => [id, index]));

    return [...props.data.data].sort(
        (a, b) =>
            (position.get(props.rowId(a)) ?? 0) -
            (position.get(props.rowId(b)) ?? 0),
    );
});

const table = useVueTable({
    get data() {
        return orderedRows.value;
    },
    get columns() {
        return columns;
    },
    getCoreRowModel: getCoreRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    state: {
        columnFilters: columnFilters.value,
        sorting: sorting.value,
    },
    onColumnFiltersChange: (updater) => {
        columnFilters.value =
            typeof updater === 'function' ? updater(columnFilters.value) : updater;
    },
    onSortingChange: (updater) => {
        sorting.value = typeof updater === 'function' ? updater(sorting.value) : updater;
    },
    manualPagination: true,
    manualSorting: true,
    manualFiltering: true,
    pageCount: Math.max(1, Math.ceil(props.data.total / props.data.per_page)),
});

const prefetchTimeouts = new Map<string, ReturnType<typeof setTimeout>>();

onMounted(() => {
    // Snapshot the list URL so a "Nieuwe …" button (which isn't a row click)
    // still returns here after saving. Row clicks and filter/sort/page changes
    // refresh this below.
    rememberListUrl();

    const params = new URLSearchParams(window.location.search);
    const filters: ColumnFiltersState = [];
    params.forEach((value, key) => {
        if (key.startsWith('filter[') && key.endsWith(']')) {
            filters.push({
                id: decodeURIComponent(key.slice(7, -1)),
                value,
            });
        }
    });
    if (filters.length > 0) {
        columnFilters.value = filters;
    }

    const sort = params.get('sort');
    if (sort) {
        const isDesc = sort.startsWith('-');
        sorting.value = [
            {
                id: decodeURIComponent(isDesc ? sort.slice(1) : sort),
                desc: isDesc,
            },
        ];
    }
});

onBeforeUnmount(() => {
    prefetchTimeouts.forEach((t) => clearTimeout(t));
    prefetchTimeouts.clear();
});

function rowClick(event: MouseEvent, row: Row<TData>) {
    if (event.button !== 0) return;
    // Capture the exact list view (filters, sort, page) the row was opened
    // from, so the detail page can send the user back to it.
    rememberListUrl();
    if (hasRowClickListener.value) {
        emit('rowClick', row.original);
    } else if (props.rowUrl) {
        const url = resolveUrl(props.rowUrl(row.original));
        if (url) router.visit(url);
    }
}

function rowMouseEnter(row: Row<TData>) {
    if (!props.rowUrl) return;
    const url = resolveUrl(props.rowUrl(row.original));
    if (!url) return;
    const existing = prefetchTimeouts.get(row.id);
    if (existing) clearTimeout(existing);
    const timeout = setTimeout(() => {
        router.prefetch(url);
        prefetchTimeouts.delete(row.id);
    }, 200);
    prefetchTimeouts.set(row.id, timeout);
}

function rowMouseLeave(row: Row<TData>) {
    const t = prefetchTimeouts.get(row.id);
    if (t) {
        clearTimeout(t);
        prefetchTimeouts.delete(row.id);
    }
}

const filterValueRefs = new Map<string, ReturnType<typeof computed<string | null>>>();

function getColumnFilterValue(columnId: string) {
    if (!filterValueRefs.has(columnId)) {
        filterValueRefs.set(
            columnId,
            computed<string | null>({
                get: () => {
                    const f = columnFilters.value.find((x) => x.id === columnId);
                    return (f?.value as string) || null;
                },
                set: (value: string | null) => {
                    if (value === '__clear__') value = null;
                    const idx = columnFilters.value.findIndex((x) => x.id === columnId);
                    if (idx >= 0) {
                        if (value) {
                            columnFilters.value[idx].value = value;
                        } else {
                            columnFilters.value.splice(idx, 1);
                        }
                    } else if (value) {
                        columnFilters.value.push({ id: columnId, value });
                    }
                },
            }),
        );
    }
    return filterValueRefs.get(columnId)!;
}

/**
 * Lets a page put its own filter control in the `#buttons` slot (a
 * segmented archive switch, for instance) while the table keeps owning the
 * filter state, the URL and the reload.
 */
const filterApi: TableFilterApi = {
    get: (columnId: string): string | null => getColumnFilterValue(columnId).value,
    set: (columnId: string, value: string | null): void => {
        getColumnFilterValue(columnId).value = value;
    },
};

const debouncedReload = debounce((filters: ColumnFiltersState) => {
    const filterParams: Record<string, string> = {};
    filters.forEach((f) => {
        if (f.value) {
            filterParams[`filter[${encodeURIComponent(f.id)}]`] = f.value as string;
        }
    });

    const url = new URL(window.location.href);
    const params = new URLSearchParams(url.search);
    for (const key of Array.from(params.keys())) {
        if (key.startsWith('filter[')) params.delete(key);
    }
    params.delete('page');
    Object.entries(filterParams).forEach(([k, v]) => params.set(k, v));

    const target = `${url.pathname}?${params.toString()}`;
    rememberListUrl(target);
    router.visit(target, {
        preserveState: true,
        preserveScroll: true,
        only: ['items'],
        replace: true,
    });
}, 300);

watch(columnFilters, (next) => debouncedReload(next), { deep: true });

const hasActiveFilters = computed(() =>
    columnFilters.value.some((f) => f.value && String(f.value).trim() !== ''),
);
const hasAnyFilterableColumn = computed(() =>
    columns.some((c) => (c.meta as CustomColumnMeta)?.filterable),
);

function clearAllFilters() {
    columnFilters.value = [];
    const url = new URL(window.location.href);
    const params = new URLSearchParams(url.search);
    for (const key of Array.from(params.keys())) {
        if (key.startsWith('filter[')) params.delete(key);
    }
    params.delete('page');
    const target = `${url.pathname}?${params.toString()}`;
    rememberListUrl(target);
    router.visit(target, {
        preserveState: true,
        preserveScroll: true,
        only: ['items'],
        replace: true,
    });
}

function handleSort(columnId: string) {
    const current = sorting.value.find((s) => s.id === columnId);
    const next: typeof sorting.value = [];
    if (!current) {
        next.push({ id: columnId, desc: false });
    } else if (!current.desc) {
        next.push({ id: columnId, desc: true });
    }
    sorting.value = next;

    const url = new URL(window.location.href);
    const params = new URLSearchParams(url.search);
    params.delete('sort');
    if (next.length > 0) {
        const s = next[0];
        params.set('sort', s.desc ? `-${encodeURIComponent(s.id)}` : encodeURIComponent(s.id));
    }
    const target = `${url.pathname}?${params.toString()}`;
    rememberListUrl(target);
    router.visit(target, {
        preserveState: true,
        preserveScroll: true,
        only: ['items'],
        replace: true,
    });
}

// ---- Slepen om te sorteren ---------------------------------------------

const draggingId = ref<string | null>(null);
const dragOverId = ref<string | null>(null);

const canReorder = computed(
    () =>
        props.reorderable &&
        sorting.value.length === 0 &&
        !hasActiveFilters.value,
);

const reorderHint = computed(() =>
    canReorder.value
        ? 'Sleep om de volgorde te wijzigen'
        : 'Slepen kan alleen zonder filter of sortering',
);

function onRowDragStart(event: DragEvent, id: string): void {
    if (!canReorder.value) {
        return;
    }

    draggingId.value = id;

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', id);
    }
}

function onRowDragOver(event: DragEvent, id: string): void {
    if (!draggingId.value || draggingId.value === id) {
        return;
    }

    event.preventDefault();

    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }

    dragOverId.value = id;
}

function onRowDragLeave(id: string): void {
    if (dragOverId.value === id) {
        dragOverId.value = null;
    }
}

function onRowDrop(event: DragEvent, targetId: string): void {
    event.preventDefault();
    const sourceId = draggingId.value;
    onRowDragEnd();

    if (!sourceId || sourceId === targetId) {
        return;
    }

    const ordered = table
        .getRowModel()
        .rows.map((row) => props.rowId(row.original));
    const from = ordered.indexOf(sourceId);
    const to = ordered.indexOf(targetId);

    if (from < 0 || to < 0) {
        return;
    }

    ordered.splice(to, 0, ordered.splice(from, 1)[0]);
    optimisticOrder.value = ordered;
    emit('reorder', ordered);
}

function onRowDragEnd(): void {
    draggingId.value = null;
    dragOverId.value = null;
}
</script>

<template>
    <div>
        <div
            v-if="label || $slots.buttons || ($slots['bulk-actions'] && selected.length > 0)"
            class="flex items-center justify-between pb-4"
        >
            <div v-if="label" class="flex items-baseline gap-2">
                <span class="text-2xl font-semibold tabular-nums">{{ data.total }}</span>
                <span class="text-sm text-muted-foreground">{{ label }}</span>
                <span
                    v-if="selectable && selected.length > 0"
                    class="text-sm text-muted-foreground"
                >
                    · {{ selected.length }} geselecteerd
                </span>
            </div>
            <div v-else />
            <div class="flex items-center gap-2">
                <slot
                    v-if="selectable && selected.length > 0"
                    name="bulk-actions"
                    :selected="selected"
                    :clear="clearSelection"
                />
                <slot name="buttons" :filters="filterApi" />
            </div>
        </div>

        <div
            class="overflow-x-auto rounded-xl border border-border bg-card"
        >
            <Table>
                <TableHeader>
                    <TableRow
                        v-for="headerGroup in table.getHeaderGroups()"
                        :key="headerGroup.id"
                    >
                        <TableHead
                            v-if="reorderable"
                            class="bg-muted w-8 align-top py-3"
                        />
                        <TableHead
                            v-if="selectable"
                            class="bg-muted w-10 align-top py-3"
                        >
                            <Checkbox
                                :model-value="headerCheckboxState"
                                aria-label="Select all visible rows"
                                @update:model-value="toggleAllVisible"
                            />
                        </TableHead>
                        <TableHead
                            v-for="(header, headerIndex) in headerGroup.headers"
                            :key="header.id"
                            class="bg-muted align-top py-3"
                        >
                            <div class="flex flex-col gap-2">
                                <button
                                    v-if="!header.isPlaceholder"
                                    type="button"
                                    class="flex items-center gap-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground"
                                    :class="[
                                        (header.column.columnDef.meta as CustomColumnMeta)?.sortable
                                            ? 'cursor-pointer hover:text-foreground'
                                            : 'cursor-default',
                                    ]"
                                    :title="
                                        (header.column.columnDef.meta as CustomColumnMeta)?.sortable
                                            ? 'Click to sort by this column'
                                            : undefined
                                    "
                                    @click="
                                        (header.column.columnDef.meta as CustomColumnMeta)
                                            ?.sortable && handleSort(header.column.id)
                                    "
                                >
                                    <FlexRender
                                        v-if="header.column.columnDef.header"
                                        :render="header.column.columnDef.header"
                                        :props="header.getContext()"
                                    />
                                    <template
                                        v-if="
                                            (header.column.columnDef.meta as CustomColumnMeta)
                                                ?.sortable
                                        "
                                    >
                                        <!--
                                            Alleen de actieve sortering krijgt een pijl. De
                                            dubbele pijl op elke kolom kostte 23 px per kolom,
                                            waardoor brede lijsten (evenementen: veertien
                                            kolommen) niet meer in beeld pasten; de hover-kleur
                                            en de tooltip zeggen al dat de kop klikbaar is.
                                        -->
                                        <ArrowUp
                                            v-if="
                                                sorting.find((s) => s.id === header.column.id)
                                                    ?.desc === false
                                            "
                                            class="size-3.5 text-primary"
                                        />
                                        <ArrowDown
                                            v-else-if="sorting.find((s) => s.id === header.column.id)"
                                            class="size-3.5 text-primary"
                                        />
                                    </template>
                                </button>

                                <div
                                    v-if="
                                        !header.isPlaceholder &&
                                        ((header.column.columnDef.meta as CustomColumnMeta)
                                            ?.filterable ||
                                            (headerIndex === headerGroup.headers.length - 1 &&
                                                hasAnyFilterableColumn))
                                    "
                                    class="flex items-center gap-2"
                                >
                                    <Input
                                        v-if="
                                            (header.column.columnDef.meta as CustomColumnMeta)
                                                ?.filterable &&
                                            (header.column.columnDef.meta as CustomColumnMeta)
                                                ?.filterType === 'text'
                                        "
                                        v-model="getColumnFilterValue(header.column.id).value as any"
                                        class="h-8 w-full bg-white text-xs md:text-xs"
                                        :placeholder="
                                            (header.column.columnDef.meta as CustomColumnMeta)
                                                ?.filterPlaceholder ?? 'Search...'
                                        "
                                        :data-test="`filter-${header.column.id}`"
                                    />
                                    <Select
                                        v-else-if="
                                            (header.column.columnDef.meta as CustomColumnMeta)
                                                ?.filterable &&
                                            (header.column.columnDef.meta as CustomColumnMeta)
                                                ?.filterType === 'select'
                                        "
                                        v-model="getColumnFilterValue(header.column.id).value as any"
                                    >
                                        <SelectTrigger class="h-8 w-full bg-white text-xs">
                                            <SelectValue
                                                :placeholder="
                                                    (header.column.columnDef.meta as CustomColumnMeta)
                                                        ?.filterPlaceholder ?? 'All'
                                                "
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-if="getColumnFilterValue(header.column.id).value"
                                                value="__clear__"
                                            >
                                                {{
                                                    (header.column.columnDef.meta as CustomColumnMeta)
                                                        ?.filterPlaceholder ?? 'All'
                                                }}
                                            </SelectItem>
                                            <SelectItem
                                                v-for="option in (Object.values(
                                                    (
                                                        header.column.columnDef
                                                            .meta as CustomColumnMeta
                                                    )?.filterOptions ?? {},
                                                ) as EnumOption[])"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                <div class="flex items-center gap-2">
                                                    <span
                                                        v-if="option.colorClass"
                                                        class="size-2.5 rounded-full"
                                                        :class="option.colorClass"
                                                    />
                                                    <span>{{ option.label }}</span>
                                                </div>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <Button
                                        v-if="
                                            headerIndex === headerGroup.headers.length - 1 &&
                                            hasAnyFilterableColumn
                                        "
                                        variant="outline"
                                        size="icon"
                                        class="size-8 shrink-0 text-rose-600"
                                        :class="hasActiveFilters ? 'visible' : 'invisible'"
                                        @click="clearAllFilters"
                                    >
                                        <X class="size-4" />
                                    </Button>
                                </div>
                            </div>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-if="table.getRowModel().rows?.length">
                        <TableRow
                            v-for="row in table.getRowModel().rows"
                            :key="row.id"
                            :class="[
                                'transition-colors hover:bg-muted/50',
                                rowIsClickable ? 'cursor-pointer' : '',
                                draggingId === rowId(row.original)
                                    ? 'opacity-40'
                                    : '',
                                dragOverId === rowId(row.original)
                                    ? 'outline outline-2 -outline-offset-2 outline-primary'
                                    : '',
                                rowClass?.(row.original),
                            ]"
                            :draggable="canReorder"
                            @click="rowClick($event, row)"
                            @mouseenter="rowMouseEnter(row)"
                            @mouseleave="rowMouseLeave(row)"
                            @dragstart="onRowDragStart($event, rowId(row.original))"
                            @dragover="onRowDragOver($event, rowId(row.original))"
                            @dragleave="onRowDragLeave(rowId(row.original))"
                            @drop="onRowDrop($event, rowId(row.original))"
                            @dragend="onRowDragEnd"
                        >
                            <TableCell
                                v-if="reorderable"
                                class="w-8 text-muted-foreground"
                                :title="reorderHint"
                                @click.stop
                            >
                                <GripVertical
                                    :class="[
                                        'size-4',
                                        canReorder
                                            ? 'cursor-grab active:cursor-grabbing'
                                            : 'opacity-40',
                                    ]"
                                />
                            </TableCell>
                            <TableCell
                                v-if="selectable"
                                class="w-10"
                                @click.stop
                            >
                                <Checkbox
                                    :model-value="selected.includes(rowId(row.original))"
                                    :aria-label="`Select row`"
                                    @update:model-value="(v) => toggleRow(rowId(row.original), v)"
                                />
                            </TableCell>
                            <TableCell v-for="cell in row.getVisibleCells()" :key="cell.id">
                                <FlexRender
                                    :render="cell.column.columnDef.cell"
                                    :props="cell.getContext()"
                                />
                            </TableCell>
                        </TableRow>
                    </template>
                    <template v-else>
                        <TableRow>
                            <TableCell
                                :colspan="
                                    columns.length +
                                    (selectable ? 1 : 0) +
                                    (reorderable ? 1 : 0)
                                "
                                class="h-24 text-center text-muted-foreground"
                            >
                                <slot name="empty">No results</slot>
                            </TableCell>
                        </TableRow>
                    </template>
                </TableBody>
            </Table>

            <DataTablePagination :data="data" />
        </div>
    </div>
</template>
