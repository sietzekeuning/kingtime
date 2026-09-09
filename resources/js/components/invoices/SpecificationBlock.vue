<script setup lang="ts">
import { Check, Copy } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';

/**
 * The plain-text hour specification in a monospace block with a copy
 * button, so it can be pasted into an email or a Moneybird note.
 */
const props = defineProps<{
    text: string;
}>();

const copied = ref(false);

async function copy() {
    try {
        await navigator.clipboard.writeText(props.text);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        copied.value = false;
    }
}
</script>

<template>
    <div class="relative">
        <Button
            variant="outline"
            size="sm"
            class="absolute top-2 right-2"
            type="button"
            @click="copy"
        >
            <Check v-if="copied" class="size-4 text-emerald-600" />
            <Copy v-else class="size-4" />
            {{ copied ? 'Copied' : 'Copy' }}
        </Button>
        <pre
            class="bg-muted/50 border-border max-h-[32rem] overflow-auto rounded-md border p-4 pr-28 font-mono text-xs leading-relaxed whitespace-pre-wrap"
            >{{ text }}</pre>
    </div>
</template>
