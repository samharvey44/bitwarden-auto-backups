<script setup>
const model = defineModel({ type: [String, Number, null] });

defineProps({
    invalid: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <input
        v-model="model"
        :aria-invalid="invalid || undefined"
        class="block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm shadow-xs placeholder:text-zinc-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:outline-hidden disabled:opacity-50 aria-invalid:border-red-500 aria-invalid:focus:ring-red-500/20 dark:border-zinc-700 dark:bg-zinc-900 dark:placeholder:text-zinc-500"
    />
</template>
