<script setup>
const model = defineModel({ type: [String, Number, null] });

defineProps({
    options: {
        type: Array,
        required: true,
    },
    invalid: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <select
        v-model="model"
        :aria-invalid="invalid || undefined"
        class="block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm shadow-xs focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:outline-hidden aria-invalid:border-red-500 dark:border-zinc-700 dark:bg-zinc-900"
    >
        <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
    </select>
</template>
