<script setup>
defineProps({
    tone: {
        type: String,
        default: 'info',
        validator: (value) => ['info', 'success', 'warning', 'error'].includes(value),
    },
});

const tones = {
    info: 'border-blue-200 bg-blue-50 text-blue-900 dark:border-blue-900/60 dark:bg-blue-950/50 dark:text-blue-200',
    success: 'border-green-200 bg-green-50 text-green-900 dark:border-green-900/60 dark:bg-green-950/50 dark:text-green-200',
    warning: 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/50 dark:text-amber-200',
    error: 'border-red-200 bg-red-50 text-red-900 dark:border-red-900/60 dark:bg-red-950/50 dark:text-red-200',
};
</script>

<template>
    <div :class="tones[tone]" class="rounded-md border px-4 py-3 text-sm" :role="tone === 'error' ? 'alert' : 'status'">
        <slot />
    </div>
</template>
