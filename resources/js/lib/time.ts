import dayjs from 'dayjs';

/** "2026-09-09" → "9 Sep 2026" (or any dayjs format). Empty input renders as "". */
export function formatDate(
    value: string | null | undefined,
    format = 'D MMM YYYY',
): string {
    if (!value) {
        return '';
    }

    return dayjs(value).format(format);
}

/** Shifts a `Y-m-d` date by a number of days, staying in `Y-m-d`. */
export function addDays(date: string, days: number): string {
    return dayjs(date).add(days, 'day').format('YYYY-MM-DD');
}

/** Whole seconds elapsed since an ISO timestamp, never negative. */
export function secondsSince(
    startedAt: string,
    now: number = Date.now(),
): number {
    return Math.max(0, Math.floor((now - Date.parse(startedAt)) / 1000));
}

/** 3725 → "01:02:05"; the running-timer display. */
export function formatDuration(totalSeconds: number): string {
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    return [hours, minutes, seconds]
        .map((part) => String(part).padStart(2, '0'))
        .join(':');
}

/** Shifts a `Y-m-d` date by a number of months, landing on the first of that month. */
export function addMonths(date: string, months: number): string {
    return dayjs(date)
        .add(months, 'month')
        .startOf('month')
        .format('YYYY-MM-DD');
}

/**
 * Reads the hours typed into a timesheet cell: "1,5", "1.5", "1:30" and
 * "0,25" all work. Empty input is `null` (nothing logged); anything else
 * that is not a number is `undefined` so the cell can refuse it.
 */
export function parseHoursInput(value: string): number | null | undefined {
    const trimmed = value.trim();

    if (trimmed === '') {
        return null;
    }

    const clock = /^(\d{1,2}):([0-5]?\d)$/.exec(trimmed);

    if (clock) {
        return Number(clock[1]) + Number(clock[2]) / 60;
    }

    const decimal = Number(trimmed.replace(',', '.'));

    if (!Number.isFinite(decimal) || decimal < 0 || decimal > 24) {
        return undefined;
    }

    return decimal;
}
