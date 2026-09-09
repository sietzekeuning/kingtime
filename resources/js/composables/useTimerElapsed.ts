import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import type { MaybeRefOrGetter } from 'vue';
import { toValue } from 'vue';
import { formatDuration, secondsSince } from '@/lib/time';

/**
 * A once-per-second clock for a running timer. Elapsed time is derived from
 * `timer_started_at`, not accumulated client-side, so a tab that was asleep
 * catches up on its own.
 */
export function useTimerElapsed(
    startedAt: MaybeRefOrGetter<string | null | undefined>,
) {
    const now = ref(Date.now());
    let interval: ReturnType<typeof setInterval> | undefined;

    onMounted(() => {
        interval = setInterval(() => {
            now.value = Date.now();
        }, 1000);
    });

    onBeforeUnmount(() => {
        if (interval) {
            clearInterval(interval);
        }
    });

    const seconds = computed(() => {
        const started = toValue(startedAt);

        return started ? secondsSince(started, now.value) : 0;
    });

    const formatted = computed(() => formatDuration(seconds.value));

    return { seconds, formatted };
}
