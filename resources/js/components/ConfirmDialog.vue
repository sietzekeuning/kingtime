<script setup lang="ts">
import { computed } from 'vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { buttonVariants } from '@/components/ui/button';
import { confirmState, resolveConfirm } from '@/composables/useConfirm';
import { cn } from '@/lib/utils';

const open = computed({
    get: () => confirmState.open,
    set: (value) => {
        // Closing via overlay click or Escape resolves the awaiter as cancelled.
        if (!value) {
            resolveConfirm(false);
        }
    },
});

const actionClass = computed(() =>
    cn(
        confirmState.variant === 'destructive' &&
            buttonVariants({ variant: 'destructive' }),
    ),
);
</script>

<template>
    <AlertDialog v-model:open="open">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ confirmState.title }}</AlertDialogTitle>
                <AlertDialogDescription v-if="confirmState.description">
                    {{ confirmState.description }}
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel @click="resolveConfirm(false)">
                    {{ confirmState.cancelLabel }}
                </AlertDialogCancel>
                <!--
                    `.capture`, not a plain `@click`: AlertDialogAction is a
                    reka-ui DialogClose, so it fires `onOpenChange(false)` on
                    click, which flows through `v-model:open` into the setter
                    above and calls `resolveConfirm(false)`. On the same click
                    that bubble-phase close raced our `resolveConfirm(true)` and
                    often won, so every confirmation resolved as *cancelled* and
                    the caller's action silently never ran. Resolving in the
                    capture phase runs `true` before reka-ui's bubble-phase
                    close, so the confirm decision always wins.
                -->
                <AlertDialogAction
                    :class="actionClass"
                    @click.capture="resolveConfirm(true)"
                >
                    {{ confirmState.confirmLabel }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
