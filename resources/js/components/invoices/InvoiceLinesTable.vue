<script setup lang="ts">
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatEuro } from '@/lib/formatters';
import { formatHours } from '@/lib/utils';
import type { InvoiceLineData } from '@/types/generated';

/**
 * The invoice specification lines: one row per project, task and rate.
 * Shared by the prepare preview and the invoice detail page.
 */
defineProps<{
    lines: InvoiceLineData[];
    subtotal: string;
    totalHours: string;
    /** Shown as a second total row once Moneybird has added VAT. */
    total?: string | null;
}>();
</script>

<template>
    <div class="overflow-x-auto">
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Description</TableHead>
                    <TableHead class="text-right">Hours</TableHead>
                    <TableHead class="text-right">Rate</TableHead>
                    <TableHead class="text-right">Amount</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="line in lines" :key="line.sort_order">
                    <TableCell class="font-medium">
                        {{ line.description }}
                        <span
                            v-if="Number.parseFloat(line.unit_price) === 0"
                            class="ml-2 text-xs font-normal text-amber-700"
                        >
                            no rate
                        </span>
                    </TableCell>
                    <TableCell class="text-right tabular-nums">
                        {{ formatHours(line.quantity) }}
                    </TableCell>
                    <TableCell class="text-right tabular-nums">
                        {{ formatEuro(line.unit_price) }}
                    </TableCell>
                    <TableCell class="text-right tabular-nums">
                        {{ formatEuro(line.amount) }}
                    </TableCell>
                </TableRow>
                <TableRow v-if="lines.length === 0">
                    <TableCell
                        colspan="4"
                        class="text-muted-foreground text-center"
                    >
                        No lines
                    </TableCell>
                </TableRow>
            </TableBody>
            <TableFooter>
                <TableRow>
                    <TableCell>Subtotal (excl. VAT)</TableCell>
                    <TableCell class="text-right tabular-nums">
                        {{ formatHours(totalHours) }}
                    </TableCell>
                    <TableCell />
                    <TableCell class="text-right tabular-nums">
                        {{ formatEuro(subtotal) }}
                    </TableCell>
                </TableRow>
                <TableRow v-if="total && total !== subtotal">
                    <TableCell>Total (incl. VAT, from Moneybird)</TableCell>
                    <TableCell />
                    <TableCell />
                    <TableCell class="text-right font-semibold tabular-nums">
                        {{ formatEuro(total) }}
                    </TableCell>
                </TableRow>
            </TableFooter>
        </Table>
    </div>
</template>
