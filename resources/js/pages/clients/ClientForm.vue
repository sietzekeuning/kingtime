<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Save, Trash2 } from '@lucide/vue';
import Form from '@/components/Form.vue';
import FormRow from '@/components/FormRow.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useConfirmDelete } from '@/composables/useConfirmDelete';
import clients from '@/routes/clients';
import type { ClientData } from '@/types/generated';

const props = defineProps<{
    client: ClientData;
}>();

const isNew = props.client.id === null;

defineOptions({
    layout: (page: { props: { client: ClientData } }) => ({
        breadcrumbs: [
            { title: 'Clients', href: clients.index() },
            {
                title: page.props.client.id
                    ? page.props.client.name
                    : 'New client',
                href: page.props.client.id
                    ? clients.edit(page.props.client.id)
                    : clients.create(),
            },
        ],
    }),
});

const form = useForm({ ...props.client });

const { confirmDelete } = useConfirmDelete();

function submit() {
    if (isNew) {
        form.post(clients.store().url);

        return;
    }

    form.patch(clients.update(props.client.id!).url, { preserveScroll: true });
}

function destroy() {
    void confirmDelete(clients.destroy(props.client.id!).url, {
        title: `Delete client "${props.client.name}"?`,
        description:
            'Projects and time entries of this client are deleted too. This cannot be undone.',
    });
}
</script>

<template>
    <Head :title="isNew ? 'New client' : client.name" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <PageHeader
            :title="isNew ? 'New client' : client.name"
            :subtitle="
                isNew
                    ? 'Add a company you work for.'
                    : `${client.projects_count ?? 0} projects`
            "
            :back-href="clients.index()"
            back-label="Clients"
        >
            <template #actions>
                <Button
                    v-if="!isNew"
                    variant="outline"
                    class="text-destructive"
                    @click="destroy"
                >
                    <Trash2 class="size-4" />
                    Delete
                </Button>
                <Button
                    form="client-form"
                    type="submit"
                    :disabled="form.processing"
                >
                    <Save class="size-4" />
                    Save
                </Button>
            </template>
        </PageHeader>

        <Card class="max-w-3xl">
            <CardContent>
                <Form id="client-form" :form="form" @submit="submit">
                    <div class="space-y-4">
                        <FormRow label="Name" field="name" required>
                            <Input v-model="form.name" autofocus />
                        </FormRow>
                        <FormRow label="Email" field="email">
                            <Input v-model="form.email" type="email" />
                        </FormRow>
                        <FormRow label="Address" field="address">
                            <Textarea v-model="form.address" rows="3" />
                        </FormRow>
                        <FormRow label="Currency" field="currency">
                            <Input v-model="form.currency" class="w-24" />
                        </FormRow>
                        <FormRow label="Notes" field="notes">
                            <Textarea v-model="form.notes" rows="3" />
                        </FormRow>
                        <FormRow label="Active" field="is_active" inline>
                            <Switch v-model="form.is_active" />
                        </FormRow>
                        <FormRow
                            v-if="client.moneybird_contact_id"
                            label="Moneybird contact"
                        >
                            <span class="text-muted-foreground text-sm">
                                #{{ client.moneybird_contact_id }}
                            </span>
                        </FormRow>
                        <FormRow v-if="client.harvest_id" label="Harvest id">
                            <span class="text-muted-foreground text-sm">
                                #{{ client.harvest_id }}
                            </span>
                        </FormRow>
                    </div>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>
