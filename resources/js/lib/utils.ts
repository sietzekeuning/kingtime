import type { InertiaLinkProps } from '@inertiajs/vue3';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
    return typeof href === 'string' ? href : href?.url;
}

export function moneyFormatter(
    currency: string,
    options: Intl.NumberFormatOptions = {},
): Intl.NumberFormat {
    return new Intl.NumberFormat('nl-NL', {
        style: 'currency',
        currency,
        ...options,
    });
}

/** "12.50" → "12,50"; hours are shown with two decimals everywhere. */
export function formatHours(value: string | number | null | undefined): string {
    const amount = typeof value === 'string' ? Number.parseFloat(value) : value;

    if (amount === null || amount === undefined || Number.isNaN(amount)) {
        return '0,00';
    }

    return new Intl.NumberFormat('nl-NL', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount);
}
