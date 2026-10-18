<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import AppLayout from './AppLayout.vue';

const page = usePage();

const sections = [
    { label: 'Profile', href: '/settings/profile' },
    { label: 'Password', href: '/settings/password' },
    { label: 'Two-factor authentication', href: '/settings/two-factor' },
];
</script>

<template>
    <AppLayout title="Settings">
        <div class="flex flex-col gap-8 md:flex-row">
            <nav class="flex shrink-0 gap-1 overflow-x-auto md:w-56 md:flex-col">
                <Link
                    v-for="section in sections"
                    :key="section.href"
                    :href="section.href"
                    :class="
                        page.url.startsWith(section.href)
                            ? 'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100'
                            : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100'
                    "
                    class="rounded-md px-3 py-2 text-sm font-medium whitespace-nowrap"
                >
                    {{ section.label }}
                </Link>
            </nav>

            <section class="flex max-w-xl flex-1 flex-col gap-6">
                <slot />
            </section>
        </div>
    </AppLayout>
</template>
