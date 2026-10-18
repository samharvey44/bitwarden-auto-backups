<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppAlert from '../components/AppAlert.vue';
import AppLogo from '../components/AppLogo.vue';
import { logout } from '../routes';
import settings from '../routes/settings';
import vaultRoutes from '../routes/vaults';

defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: null,
    },
    back: {
        type: Object,
        default: null,
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);

const navigation = [
    { label: 'Vaults', route: vaultRoutes.index, active: (url) => url.startsWith(vaultRoutes.index.url()) },
    {
        label: 'Settings',
        route: settings.profile,
        active: (url) => [settings.profile, settings.password, settings.twoFactor].some((route) => url.startsWith(route.url())),
    },
];
</script>

<template>
    <Head :title="title" />

    <div class="flex min-h-screen flex-col">
        <header class="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 px-4 py-3 sm:px-6">
                <div class="flex items-center gap-6">
                    <Link :href="vaultRoutes.index()" class="flex items-center gap-2.5 font-semibold tracking-tight">
                        <AppLogo />
                        <span class="hidden sm:inline">{{ page.props.appName }}</span>
                    </Link>

                    <nav class="flex items-center gap-1">
                        <Link
                            v-for="item in navigation"
                            :key="item.label"
                            :href="item.route()"
                            :class="
                                item.active(page.url)
                                    ? 'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100'
                                    : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100'
                            "
                            class="rounded-md px-3 py-1.5 text-sm font-medium"
                        >
                            {{ item.label }}
                        </Link>
                    </nav>
                </div>

                <div class="flex items-center gap-3 text-sm">
                    <span class="hidden text-zinc-500 sm:inline dark:text-zinc-400">{{ user.email }}</span>
                    <Link
                        :href="logout()"
                        as="button"
                        class="rounded-md px-3 py-1.5 font-medium text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                    >
                        Log out
                    </Link>
                </div>
            </div>
        </header>

        <main class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 px-4 py-8 sm:px-6">
            <AppAlert v-if="page.flash?.success" tone="success">{{ page.flash?.success }}</AppAlert>
            <AppAlert v-if="page.flash?.error" tone="error">{{ page.flash?.error }}</AppAlert>

            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <Link
                        v-if="back"
                        :href="back.href"
                        class="inline-flex w-fit items-center gap-1 text-sm text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100"
                    >
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="m15 18-6-6 6-6" />
                        </svg>
                        {{ back.label }}
                    </Link>
                    <h1 class="text-2xl font-semibold tracking-tight">{{ title }}</h1>
                    <p v-if="description" class="text-sm text-zinc-500 dark:text-zinc-400">{{ description }}</p>
                </div>
                <slot name="actions" />
            </div>

            <slot />
        </main>
    </div>
</template>
