<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import FaIcon from '@/components/FaIcon.vue';
import { dashboard, home, login, register } from '@/routes';

/**
 * Shell of the public pages (kingtime.nl for visitors): a slim top bar with
 * the wordmark and the sign-in links, the page, and a short footer. The
 * pages tell it whether sign-up is open through the `canRegister` prop.
 */
const page = usePage<{ canRegister?: boolean }>();
const canRegister = computed(() => page.props.canRegister === true);
const isSignedIn = computed(() => page.props.auth?.user != null);
const year = new Date().getFullYear();
</script>

<template>
    <div class="bg-background text-foreground min-h-svh">
        <header
            class="mx-auto flex max-w-6xl items-center justify-between px-6 py-5"
        >
            <Link :href="home()" class="flex items-center gap-2.5">
                <span
                    class="bg-primary text-primary-foreground flex size-8 items-center justify-center rounded-lg"
                >
                    <AppLogoIcon class="size-5" />
                </span>
                <span class="text-base font-bold tracking-tight">Kingtime</span>
            </Link>

            <nav class="flex items-center gap-1 text-sm">
                <a
                    href="#features"
                    class="text-muted-foreground hover:text-foreground hidden rounded-md px-3 py-1.5 transition sm:inline-block"
                >
                    Features
                </a>
                <a
                    href="#mac"
                    class="text-muted-foreground hover:text-foreground hidden rounded-md px-3 py-1.5 transition sm:inline-block"
                >
                    Mac app
                </a>
                <a
                    href="https://github.com/sietzekeuning/kingtime"
                    class="text-muted-foreground hover:text-foreground hidden items-center gap-1.5 rounded-md px-3 py-1.5 transition sm:inline-flex"
                >
                    <FaIcon icon="github" weight="brands" />
                    GitHub
                </a>
                <Link
                    v-if="isSignedIn"
                    :href="dashboard()"
                    class="bg-foreground text-background hover:bg-foreground/90 ml-1 inline-flex items-center gap-2 rounded-md px-3.5 py-1.5 font-medium transition"
                >
                    Go to dashboard
                    <FaIcon icon="arrow-right" class="text-xs" />
                </Link>
                <template v-else>
                    <Link
                        :href="login()"
                        class="text-foreground hover:bg-accent rounded-md px-3 py-1.5 font-medium transition"
                    >
                        Log in
                    </Link>
                    <Link
                        v-if="canRegister"
                        :href="register()"
                        class="bg-foreground text-background hover:bg-foreground/90 ml-1 rounded-md px-3.5 py-1.5 font-medium transition"
                    >
                        Create account
                    </Link>
                </template>
            </nav>
        </header>

        <main>
            <slot />
        </main>

        <footer
            class="border-border mx-auto mt-24 flex max-w-6xl flex-col gap-3 border-t px-6 py-8 text-sm sm:flex-row sm:items-center sm:justify-between"
        >
            <p class="text-muted-foreground">
                Kingtime is open source (MIT). © {{ year }} Sietze Keuning.
            </p>
            <div class="text-muted-foreground flex items-center gap-4">
                <a
                    href="https://github.com/sietzekeuning/kingtime"
                    class="hover:text-foreground inline-flex items-center gap-1.5"
                >
                    <FaIcon icon="github" weight="brands" />
                    Source
                </a>
                <a
                    href="https://github.com/sietzekeuning/kingtime-mac"
                    class="hover:text-foreground inline-flex items-center gap-1.5"
                >
                    <FaIcon icon="apple" weight="brands" />
                    Mac app source
                </a>
                <Link :href="login()" class="hover:text-foreground"
                    >Log in</Link
                >
            </div>
        </footer>
    </div>
</template>
