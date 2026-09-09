<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Domain/User/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Profile settings',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head title="Profile settings" />

    <h1 class="sr-only">Profile settings</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Profile"
            description="Update your name and email address"
        />

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    class="mt-1 block w-full"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                    placeholder="Full name"
                />
                <InputError class="mt-2" :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    name="email"
                    :default-value="user.email"
                    required
                    autocomplete="username"
                    placeholder="Email address"
                />
                <InputError class="mt-2" :message="errors.email" />
            </div>

            <div v-if="page.props.mustVerifyEmail && !user.email_verified_at">
                <p class="text-muted-foreground -mt-4 text-sm">
                    Your email address is unverified.
                    <Link
                        :href="send()"
                        as="button"
                        class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                    >
                        Click here to re-send the verification email.
                    </Link>
                </p>

                <div
                    v-if="page.props.status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    A new verification link has been sent to your email address.
                </div>
            </div>

            <Heading
                variant="small"
                title="Invoice details"
                description="Printed as the sender on the invoice PDF. Leave empty if you only invoice through Moneybird."
            />

            <div class="grid gap-2">
                <Label for="company_name">Company name</Label>
                <Input
                    id="company_name"
                    class="mt-1 block w-full"
                    name="company_name"
                    :default-value="user.company_name ?? ''"
                    autocomplete="organization"
                    placeholder="Your business name"
                />
                <InputError class="mt-2" :message="errors.company_name" />
            </div>

            <div class="grid gap-2">
                <Label for="company_address">Address</Label>
                <textarea
                    id="company_address"
                    name="company_address"
                    rows="3"
                    class="border-input bg-background placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 mt-1 block w-full rounded-md border px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                    placeholder="Street 1&#10;1234 AB City"
                    >{{ user.company_address ?? '' }}</textarea>
                <InputError class="mt-2" :message="errors.company_address" />
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="grid gap-2">
                    <Label for="vat_number">VAT number</Label>
                    <Input
                        id="vat_number"
                        class="mt-1 block w-full"
                        name="vat_number"
                        :default-value="user.vat_number ?? ''"
                        placeholder="NL123456789B01"
                    />
                    <InputError class="mt-2" :message="errors.vat_number" />
                </div>
                <div class="grid gap-2">
                    <Label for="coc_number">Chamber of Commerce</Label>
                    <Input
                        id="coc_number"
                        class="mt-1 block w-full"
                        name="coc_number"
                        :default-value="user.coc_number ?? ''"
                        placeholder="12345678"
                    />
                    <InputError class="mt-2" :message="errors.coc_number" />
                </div>
                <div class="grid gap-2">
                    <Label for="iban">IBAN</Label>
                    <Input
                        id="iban"
                        class="mt-1 block w-full"
                        name="iban"
                        :default-value="user.iban ?? ''"
                        placeholder="NL00 BANK 0123 4567 89"
                    />
                    <InputError class="mt-2" :message="errors.iban" />
                </div>
            </div>

            <div class="flex items-center gap-4">
                <Button :disabled="processing" data-test="update-profile-button"
                    >Save</Button
                >
            </div>
        </Form>
    </div>

    <DeleteUser />
</template>
