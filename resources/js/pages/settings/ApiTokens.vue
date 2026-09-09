<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { KeyRound, Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import Form from '@/components/Form.vue';
import FormRow from '@/components/FormRow.vue';
import Heading from '@/components/Heading.vue';
import SpecificationBlock from '@/components/invoices/SpecificationBlock.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useConfirmDelete } from '@/composables/useConfirmDelete';
import { formatDate } from '@/lib/time';
import apiTokens from '@/routes/api-tokens';

type ApiToken = {
    id: number;
    name: string;
    last_used_at: string | null;
    created_at: string | null;
};

const props = defineProps<{
    tokens: ApiToken[];
    /** Only present right after creating a token; shown once. */
    plainTextToken: string | null;
    mcpUrl: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'API tokens', href: apiTokens.index() }],
    },
});

const form = useForm({ name: '' });

function submit() {
    form.post(apiTokens.store.url(), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

const { confirmDelete } = useConfirmDelete();

function revoke(token: ApiToken) {
    void confirmDelete(apiTokens.destroy.url(token.id), {
        title: `Revoke "${token.name}"?`,
        description:
            'Any LLM client using this token loses access immediately.',
    });
}

const tokenPlaceholder = '<token>';
const shownToken = computed(() => props.plainTextToken ?? tokenPlaceholder);

const claudeCodeSnippet = computed(
    () =>
        `claude mcp add --transport http kingtime ${props.mcpUrl} --header "Authorization: Bearer ${shownToken.value}"`,
);

const jsonSnippet = computed(() =>
    JSON.stringify(
        {
            mcpServers: {
                kingtime: {
                    url: props.mcpUrl,
                    headers: { Authorization: `Bearer ${shownToken.value}` },
                },
            },
        },
        null,
        2,
    ),
);
</script>

<template>
    <Head title="API tokens" />

    <h1 class="sr-only">API tokens</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Personal API tokens"
            description="Tokens authenticate an LLM (Claude Desktop, Claude Code, Cursor) against the Kingtime MCP server so it can log hours and prepare invoices on your behalf"
        />

        <Alert v-if="plainTextToken">
            <KeyRound class="size-4" />
            <AlertTitle>Your new token</AlertTitle>
            <AlertDescription class="space-y-2">
                <p>
                    Copy it now. For security it is not stored in a readable
                    form and will not be shown again.
                </p>
                <SpecificationBlock :text="plainTextToken" />
            </AlertDescription>
        </Alert>

        <Form :form="form" @submit="submit">
            <div class="space-y-4">
                <FormRow label="Token name" field="name" required>
                    <Input
                        id="name"
                        v-model="form.name"
                        name="name"
                        placeholder="e.g. Claude Desktop on my laptop"
                        autocomplete="off"
                        required
                    />
                </FormRow>
                <div class="flex justify-end">
                    <Button type="submit" :disabled="form.processing">
                        <Plus class="size-4" />
                        Create token
                    </Button>
                </div>
            </div>
        </Form>

        <div class="border-border overflow-hidden rounded-lg border">
            <template v-if="tokens.length">
                <div
                    v-for="token in tokens"
                    :key="token.id"
                    class="border-border flex items-center justify-between gap-4 border-b px-4 py-3 last:border-b-0"
                >
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ token.name }}</p>
                        <p class="text-muted-foreground text-sm">
                            Created {{ formatDate(token.created_at) }} ·
                            {{
                                token.last_used_at
                                    ? `last used ${formatDate(token.last_used_at, 'D MMM YYYY HH:mm')}`
                                    : 'never used'
                            }}
                        </p>
                    </div>
                    <Button
                        variant="ghost"
                        size="sm"
                        type="button"
                        class="text-destructive hover:text-destructive"
                        @click="revoke(token)"
                    >
                        <Trash2 class="size-4" />
                        Revoke
                    </Button>
                </div>
            </template>

            <div v-else class="p-8 text-center">
                <div
                    class="bg-muted mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl"
                >
                    <KeyRound class="text-muted-foreground h-7 w-7" />
                </div>
                <p class="font-medium">No API tokens yet</p>
                <p class="text-muted-foreground mt-1 text-sm">
                    Create one to connect an LLM to Kingtime
                </p>
            </div>
        </div>
    </div>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Connect your LLM"
            description="The MCP server speaks streamable HTTP and authenticates with a bearer token. Replace <token> with a token from above."
        />

        <div class="space-y-2">
            <p class="text-sm font-medium">MCP URL</p>
            <SpecificationBlock :text="mcpUrl" />
        </div>

        <div class="space-y-2">
            <p class="text-sm font-medium">Claude Code</p>
            <SpecificationBlock :text="claudeCodeSnippet" />
        </div>

        <div class="space-y-2">
            <p class="text-sm font-medium">Claude Desktop or Cursor</p>
            <p class="text-muted-foreground text-sm">
                Add this to the MCP servers configuration of the client.
            </p>
            <SpecificationBlock :text="jsonSnippet" />
        </div>

        <p class="text-muted-foreground text-sm">
            Once connected, ask your assistant to log hours ("log 2 hours of
            development on Website redesign for today"), review your week, or
            prepare an invoice. Invoices are only ever created as drafts;
            nothing is sent to a customer.
        </p>
    </div>
</template>
