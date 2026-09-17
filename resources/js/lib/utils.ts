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

/**
 * "0.50" → "0:30", "12.25" → "12:15": hours are shown as hours and minutes
 * everywhere. They are stored with two decimals, so minutes are rounded.
 */
export function formatHours(value: string | number | null | undefined): string {
    const amount = typeof value === 'string' ? Number.parseFloat(value) : value;

    if (amount === null || amount === undefined || Number.isNaN(amount)) {
        return '0:00';
    }

    const totalMinutes = Math.round(Math.abs(amount) * 60);
    const hours = Math.floor(totalMinutes / 60);
    const minutes = String(totalMinutes % 60).padStart(2, '0');

    return `${amount < 0 && totalMinutes > 0 ? '-' : ''}${hours}:${minutes}`;
}
