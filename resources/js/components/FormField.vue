<script setup>
defineProps({
    label: {
        type: String,
        required: true,
    },
    for: {
        type: String,
        required: true,
    },
    error: {
        type: String,
        default: null,
    },
    hint: {
        type: String,
        default: null,
    },
});
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <div class="flex items-baseline justify-between gap-4">
            <label :for="$props.for" class="text-sm font-medium">{{ label }}</label>
            <slot name="aside" />
        </div>
        <slot />
        <p v-if="error" class="text-sm text-red-600 dark:text-red-400">{{ error }}</p>
        <p v-else-if="hint" class="text-sm text-zinc-500 dark:text-zinc-400">{{ hint }}</p>
    </div>
</template>
