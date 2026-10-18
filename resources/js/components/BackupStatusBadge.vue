<script setup>
defineProps({
    status: {
        type: String,
        required: true,
    },
    label: {
        type: String,
        required: true,
    },
});

const tones = {
    pending: 'bg-zinc-100 text-zinc-700 ring-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:ring-zinc-700',
    running: 'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-950/50 dark:text-blue-300 dark:ring-blue-900',
    succeeded: 'bg-green-50 text-green-700 ring-green-200 dark:bg-green-950/50 dark:text-green-300 dark:ring-green-900',
    failed: 'bg-red-50 text-red-700 ring-red-200 dark:bg-red-950/50 dark:text-red-300 dark:ring-red-900',
};
</script>

<template>
    <span :class="tones[status]" class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset">
        <span v-if="status === 'running' || status === 'pending'" class="relative flex size-1.5">
            <span class="absolute inline-flex size-full animate-ping rounded-full bg-current opacity-60" />
            <span class="relative inline-flex size-1.5 rounded-full bg-current" />
        </span>
        {{ label }}
    </span>
</template>
