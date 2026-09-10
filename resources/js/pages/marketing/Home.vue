<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import FaIcon from '@/components/FaIcon.vue';
import { dashboard, login, register } from '@/routes';
import download from '@/routes/download';
import type { MacReleaseData } from '@/types/generated';

const props = defineProps<{
    canRegister: boolean;
    macRelease: MacReleaseData | null;
    repositoryUrl: string;
    macRepositoryUrl: string;
}>();

const isSignedIn = computed(() => usePage().props.auth?.user != null);

const releaseLine = computed(() => {
    if (!props.macRelease) {
        return 'macOS 14 or later · Apple silicon and Intel';
    }

    const megabytes = (props.macRelease.size_bytes / 1_000_000).toFixed(1);

    return `Version ${props.macRelease.version} · ${megabytes} MB · macOS 14 or later`;
});

const features = [
    {
        icon: 'stopwatch',
        title: 'Timesheet and timer',
        text: 'A Monday to Sunday week strip with totals per day, a timer that keeps running on every page, and a filterable table of every hour you ever logged.',
    },
    {
        icon: 'desktop',
        title: 'Menu bar app for Mac',
        text: 'Pick a client and a project, press play. The running time sits in your menu bar, and when your Mac sat idle the app asks whether to deduct it.',
    },
    {
        icon: 'file-invoice',
        title: 'Invoices through Moneybird',
        text: 'Pick a client and a period, review the unbilled hours per project, and push a draft invoice to Moneybird with the hour specification attached.',
    },
    {
        icon: 'robot',
        title: 'Built-in MCP server',
        text: 'Connect Claude, Cursor or any MCP client with a personal token and say "log two hours on the Acme website" or "prepare the September invoice".',
    },
    {
        icon: 'file-import',
        title: 'Import from Harvest',
        text: 'One button pulls your clients, projects and every time entry from Harvest. Runs are incremental and can be scheduled, so nothing is ever logged twice.',
    },
    {
        icon: 'chart-simple',
        title: 'Reports and dashboard',
        text: 'Hours, billable hours, earned and invoiced amounts per week, month or year, with a breakdown per client and project and a chart of your weeks.',
    },
];

const macPoints = [
    'Sign in once with your Kingtime account. The token lives in your keychain.',
    'Client, project, notes, play. Stopping and starting again continues the same entry.',
    'Idle for more than 15 minutes? On your return it offers to deduct that time, or to deduct and stop.',
    'Timers started on the web or through MCP show up in the menu bar too.',
    'Signed with a Developer ID and notarised by Apple, so it opens with a double-click. It updates itself.',
];
</script>

<template>
    <Head title="Time tracking and invoicing for freelancers" />

    <!-- Hero -->
    <section
        class="relative mx-auto max-w-6xl overflow-hidden px-6 pt-10 pb-16 md:pt-20 md:pb-24"
    >
        <div
            class="pointer-events-none absolute -top-40 -right-40 size-[520px] rounded-full opacity-60 blur-3xl"
            style="
                background: radial-gradient(
                    closest-side,
                    hsl(18 90% 52% / 0.28),
                    transparent
                );
            "
        />

        <div class="grid items-center gap-14 md:grid-cols-[1.05fr_1fr]">
            <div class="relative">
                <p
                    class="reveal border-border bg-card text-muted-foreground inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-medium"
                >
                    <span class="bg-primary size-1.5 rounded-full" />
                    Open source time tracking for freelancers
                </p>

                <h1
                    class="reveal font-display text-foreground mt-6 text-5xl leading-[0.95] tracking-tight text-balance md:text-7xl"
                    style="animation-delay: 60ms"
                >
                    Hours in.
                    <em class="text-primary">Invoices out.</em>
                </h1>

                <p
                    class="reveal text-muted-foreground mt-6 max-w-lg text-lg leading-relaxed text-pretty"
                    style="animation-delay: 120ms"
                >
                    Kingtime is a Harvest-style time tracker you run yourself.
                    Log hours per client and project, turn them into Moneybird
                    invoices with a clean specification, and let your LLM do the
                    bookkeeping.
                </p>

                <div
                    class="reveal mt-8 flex flex-col gap-3 sm:flex-row sm:items-center"
                    style="animation-delay: 180ms"
                >
                    <a
                        :href="download.mac().url"
                        class="bg-primary text-primary-foreground hover:bg-primary/90 inline-flex items-center justify-center gap-2.5 rounded-lg px-5 py-3 text-sm font-semibold shadow-sm transition"
                    >
                        <FaIcon
                            icon="apple"
                            weight="brands"
                            class="text-base"
                        />
                        Download for Mac
                    </a>
                    <Link
                        v-if="isSignedIn"
                        :href="dashboard()"
                        class="border-border bg-card hover:bg-accent inline-flex items-center justify-center gap-2 rounded-lg border px-5 py-3 text-sm font-semibold transition"
                    >
                        Go to your dashboard
                        <FaIcon icon="arrow-right" class="text-xs" />
                    </Link>
                    <Link
                        v-else-if="canRegister"
                        :href="register()"
                        class="border-border bg-card hover:bg-accent inline-flex items-center justify-center gap-2 rounded-lg border px-5 py-3 text-sm font-semibold transition"
                    >
                        Create your account
                        <FaIcon icon="arrow-right" class="text-xs" />
                    </Link>
                    <Link
                        v-else
                        :href="login()"
                        class="border-border bg-card hover:bg-accent inline-flex items-center justify-center gap-2 rounded-lg border px-5 py-3 text-sm font-semibold transition"
                    >
                        Log in
                        <FaIcon icon="arrow-right" class="text-xs" />
                    </Link>
                </div>

                <p
                    class="reveal text-muted-foreground mt-3 text-xs"
                    style="animation-delay: 240ms"
                >
                    {{ releaseLine }} ·
                    <a
                        :href="repositoryUrl"
                        class="hover:text-foreground underline underline-offset-2"
                        >Self-host it from GitHub</a
                    >
                </p>
            </div>

            <!-- Menu bar mock -->
            <div
                class="reveal relative mx-auto w-full max-w-md"
                style="animation-delay: 200ms"
            >
                <div
                    class="rounded-t-xl bg-[#2a2a2c] px-4 py-2 text-[13px] text-white/90 shadow-lg ring-1 ring-black/10"
                >
                    <div class="flex items-center gap-4">
                        <FaIcon icon="apple" weight="brands" class="text-sm" />
                        <span class="font-semibold">Finder</span>
                        <span class="hidden sm:inline">File</span>
                        <span class="hidden sm:inline">Edit</span>
                        <span class="hidden sm:inline">View</span>
                        <span
                            class="ml-auto flex items-center gap-1.5 rounded-md bg-white/15 px-2 py-0.5"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                class="size-3.5"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path
                                    d="m6 3.25 3.25 2L12 1.75l2.75 3.5 3.25-2-1.25 4.5h-9.5Z"
                                    fill="currentColor"
                                    stroke="none"
                                />
                                <path d="M6.25 9.5a7.5 7.5 0 1 0 11.5 0" />
                                <path d="M12 10.75v4l3 1.75" />
                            </svg>
                            <span class="tabular-nums">1:23</span>
                        </span>
                        <span class="tabular-nums">Wed 14:02</span>
                    </div>
                </div>
                <img
                    src="/images/mac/panel-light.png"
                    alt="The Kingtime panel in the macOS menu bar: a running timer on a project, the client and project pickers, a notes field and a stop button."
                    width="640"
                    height="518"
                    class="block w-full rounded-b-xl shadow-2xl ring-1 ring-black/10 dark:hidden"
                />
                <img
                    src="/images/mac/panel-dark.png"
                    alt="The Kingtime panel in the macOS menu bar, dark appearance."
                    width="640"
                    height="518"
                    class="hidden w-full rounded-b-xl shadow-2xl ring-1 ring-white/10 dark:block"
                />

                <!-- Idle prompt -->
                <div
                    class="border-border bg-card/95 absolute -bottom-8 -left-6 hidden w-64 rounded-xl border p-3 shadow-xl backdrop-blur sm:block md:-left-12"
                >
                    <div class="flex items-start gap-2.5">
                        <span
                            class="bg-primary/15 text-primary flex size-8 shrink-0 items-center justify-center rounded-lg"
                        >
                            <FaIcon icon="mug-hot" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold">
                                You were away for 23 min
                            </p>
                            <p class="text-muted-foreground text-xs">
                                Idle since 13:39 while the timer kept running.
                            </p>
                        </div>
                    </div>
                    <div class="mt-2.5 flex gap-1.5">
                        <span
                            class="bg-primary text-primary-foreground rounded-md px-2 py-1 text-xs font-medium"
                            >Deduct 23 min</span
                        >
                        <span
                            class="bg-muted rounded-md px-2 py-1 text-xs font-medium"
                            >Keep the time</span
                        >
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features -->
    <section id="features" class="mx-auto max-w-6xl scroll-mt-16 px-6 py-16">
        <div class="max-w-2xl">
            <p
                class="text-primary text-xs font-semibold tracking-[0.2em] uppercase"
            >
                What you get
            </p>
            <h2
                class="font-display mt-3 text-4xl tracking-tight text-balance md:text-5xl"
            >
                Everything between the first minute and the paid invoice.
            </h2>
        </div>

        <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <article
                v-for="feature in features"
                :key="feature.title"
                class="border-border bg-card group rounded-2xl border p-6 transition hover:-translate-y-0.5 hover:shadow-lg"
            >
                <span
                    class="bg-accent text-accent-foreground group-hover:bg-primary group-hover:text-primary-foreground flex size-10 items-center justify-center rounded-xl text-base transition"
                >
                    <FaIcon :icon="feature.icon" />
                </span>
                <h3 class="mt-5 text-lg font-semibold tracking-tight">
                    {{ feature.title }}
                </h3>
                <p class="text-muted-foreground mt-2 text-sm leading-relaxed">
                    {{ feature.text }}
                </p>
            </article>
        </div>
    </section>

    <!-- Mac app -->
    <section id="mac" class="mx-auto max-w-6xl scroll-mt-16 px-6 py-16">
        <div
            class="bg-foreground text-background grid items-center gap-12 overflow-hidden rounded-3xl p-8 md:grid-cols-2 md:p-14"
        >
            <div class="order-2 md:order-1">
                <div class="flex items-center gap-4">
                    <img
                        src="/images/mac/icon.png"
                        alt=""
                        width="64"
                        height="64"
                        class="size-16 drop-shadow-lg"
                    />
                    <div>
                        <p
                            class="text-background/60 text-xs font-semibold tracking-[0.2em] uppercase"
                        >
                            For your menu bar
                        </p>
                        <h2
                            class="font-display text-4xl tracking-tight md:text-5xl"
                        >
                            Kingtime for Mac
                        </h2>
                    </div>
                </div>

                <ul class="mt-8 space-y-3">
                    <li
                        v-for="point in macPoints"
                        :key="point"
                        class="flex gap-3 text-sm leading-relaxed"
                    >
                        <FaIcon
                            icon="circle-check"
                            class="text-primary mt-1 shrink-0"
                        />
                        <span class="text-background/85">{{ point }}</span>
                    </li>
                </ul>

                <div
                    class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center"
                >
                    <a
                        :href="download.mac().url"
                        class="bg-primary text-primary-foreground hover:bg-primary/90 inline-flex items-center justify-center gap-2.5 rounded-lg px-5 py-3 text-sm font-semibold transition"
                    >
                        <FaIcon icon="download" />
                        Download
                        {{
                            macRelease
                                ? `version ${macRelease.version}`
                                : 'for Mac'
                        }}
                    </a>
                    <a
                        :href="macRelease?.release_url ?? macRepositoryUrl"
                        class="text-background/70 hover:text-background inline-flex items-center gap-2 px-2 py-3 text-sm font-medium transition"
                    >
                        <FaIcon icon="github" weight="brands" />
                        Release notes and source
                    </a>
                </div>
                <p class="text-background/50 mt-3 text-xs">
                    {{ releaseLine }}. Open the disk image and drag Kingtime
                    into Applications.
                </p>
            </div>

            <div class="order-1 md:order-2">
                <img
                    src="/images/mac/panel-dark.png"
                    alt="The Kingtime panel with a running timer, dark appearance."
                    width="640"
                    height="518"
                    class="mx-auto w-full max-w-sm rounded-xl shadow-2xl ring-1 ring-white/10"
                />
            </div>
        </div>
    </section>

    <!-- MCP -->
    <section class="mx-auto max-w-6xl px-6 py-16">
        <div class="grid items-center gap-12 md:grid-cols-2">
            <div>
                <p
                    class="text-primary text-xs font-semibold tracking-[0.2em] uppercase"
                >
                    Your LLM does the bookkeeping
                </p>
                <h2
                    class="font-display mt-3 text-4xl tracking-tight text-balance md:text-5xl"
                >
                    "Log two hours on the Acme website for today."
                </h2>
                <p class="text-muted-foreground mt-5 text-base leading-relaxed">
                    Kingtime ships an MCP server. Create a personal token under
                    Settings, connect Claude Desktop, Claude Code or Cursor, and
                    log hours, check what is unbilled or prepare an invoice from
                    a chat. Nothing is ever sent to your customer without you:
                    invoices land in Moneybird as drafts.
                </p>
            </div>
            <div
                class="bg-card border-border overflow-x-auto rounded-2xl border p-5 font-mono text-xs leading-relaxed shadow-sm"
            >
                <p class="text-muted-foreground"># Claude Code</p>
                <p class="mt-1">
                    claude mcp add --transport http kingtime
                    https://kingtime.nl/mcp \
                </p>
                <p class="pl-6">
                    --header "Authorization: Bearer &lt;your token&gt;"
                </p>
                <p class="text-muted-foreground mt-4"># then</p>
                <p class="mt-1">&gt; what did I work on this week?</p>
                <p>&gt; prepare the September invoice for Globex</p>
                <p>&gt; start a timer on the Acme website</p>
            </div>
        </div>
    </section>

    <!-- Closing -->
    <section class="mx-auto max-w-6xl px-6 pt-8">
        <div
            class="border-border bg-card flex flex-col items-start gap-6 rounded-3xl border p-8 md:flex-row md:items-center md:justify-between md:p-12"
        >
            <div>
                <h2 class="font-display text-3xl tracking-tight md:text-4xl">
                    Free, open source, yours.
                </h2>
                <p
                    class="text-muted-foreground mt-2 max-w-xl text-sm leading-relaxed"
                >
                    Use it on kingtime.nl or run your own copy from GitHub.
                    Every account only ever sees its own clients, projects,
                    hours and invoices.
                </p>
            </div>
            <div class="flex shrink-0 flex-col gap-3 sm:flex-row">
                <Link
                    v-if="isSignedIn"
                    :href="dashboard()"
                    class="bg-primary text-primary-foreground hover:bg-primary/90 inline-flex items-center justify-center gap-2 rounded-lg px-5 py-3 text-sm font-semibold transition"
                >
                    Go to your dashboard
                </Link>
                <template v-else>
                    <Link
                        v-if="canRegister"
                        :href="register()"
                        class="bg-primary text-primary-foreground hover:bg-primary/90 inline-flex items-center justify-center gap-2 rounded-lg px-5 py-3 text-sm font-semibold transition"
                    >
                        Create your account
                    </Link>
                    <Link
                        :href="login()"
                        class="border-border hover:bg-accent inline-flex items-center justify-center gap-2 rounded-lg border px-5 py-3 text-sm font-semibold transition"
                    >
                        Log in
                    </Link>
                </template>
            </div>
        </div>
    </section>
</template>

<style scoped>
.font-display {
    font-family:
        'Instrument Serif', ui-serif, Georgia, 'Times New Roman', serif;
    font-weight: 400;
}

.reveal {
    animation: reveal 700ms cubic-bezier(0.22, 1, 0.36, 1) both;
}

@keyframes reveal {
    from {
        opacity: 0;
        transform: translateY(14px);
    }
    to {
        opacity: 1;
        transform: none;
    }
}

@media (prefers-reduced-motion: reduce) {
    .reveal {
        animation: none;
    }
}
</style>
