import { moneyFormatter } from '@/lib/utils';

/** Whole-euro formatter for chart axes and other rounded displays. */
export const eurFormatter = new Intl.NumberFormat('nl-NL', {
    style: 'currency',
    currency: 'EUR',
    maximumFractionDigits: 0,
});

const thousandsFormatter = new Intl.NumberFormat('nl-NL', {
    maximumFractionDigits: 1,
});

/**
 * Korte euro-notatie voor grafieklabels: "€350" en "€15k" in plaats van
 * "€ 15.000", zodat de as ook op een telefoon smal blijft.
 */
export function formatEuroCompact(value: number): string {
    if (Math.abs(value) < 1000) {
        return `€${Math.round(value)}`;
    }

    return `€${thousandsFormatter.format(value / 1000)}k`;
}

/**
 * Bestandsgrootte in de kortste leesbare eenheid ("842 B", "12,4 KB",
 * "3,1 MB"). Gebruikt door elke bijlagenlijst (chat, events, gebruikers).
 */
export function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

const euroCents = moneyFormatter('EUR');

/**
 * Format a monetary value as euro's with cents ("€ 12,50"). Accepts the
 * decimal strings our DTOs put on the wire as well as plain numbers;
 * null/undefined/NaN render as "€ 0,00".
 */
export function formatEuro(value: string | number | null | undefined): string {
    const amount = typeof value === 'string' ? Number.parseFloat(value) : value;

    if (amount === null || amount === undefined || Number.isNaN(amount)) {
        return euroCents.format(0);
    }

    return euroCents.format(amount);
}
