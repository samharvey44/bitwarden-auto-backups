<script setup>
defineProps({
    title: {
        type: String,
        default: null,
    },
    description: {
        type: String,
        default: null,
    },
});
</script>

<template>
    <section class="rounded-xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
        <header v-if="title" class="flex flex-col gap-1 border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
            <h2 class="font-semibold">{{ title }}</h2>
            <p v-if="description" class="text-sm text-zinc-500 dark:text-zinc-400">{{ description }}</p>
        </header>
        <div class="flex flex-col gap-5 p-6">
            <slot />
        </div>
    </section>
</template>
