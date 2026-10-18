<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import AppLogo from '../components/AppLogo.vue';

defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: null,
    },
});

const page = usePage();
</script>

<template>
    <Head :title="title" />

    <div class="flex min-h-screen flex-col items-center justify-center px-4 py-12">
        <div class="flex w-full max-w-sm flex-col gap-8">
            <div class="flex flex-col items-center gap-4 text-center">
                <AppLogo />
                <div class="flex flex-col gap-1">
                    <h1 class="text-xl font-semibold tracking-tight">{{ title }}</h1>
                    <p v-if="description" class="text-sm text-zinc-500 dark:text-zinc-400">{{ description }}</p>
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                <slot />
            </div>

            <div v-if="$slots.footer" class="text-center text-sm text-zinc-500 dark:text-zinc-400">
                <slot name="footer" />
            </div>

            <p class="text-center text-xs text-zinc-400 dark:text-zinc-500">{{ page.props.appName }}</p>
        </div>
    </div>
</template>
