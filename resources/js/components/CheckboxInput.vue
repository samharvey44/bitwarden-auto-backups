<script setup>
const model = defineModel({ type: [Boolean, Array] });
</script>

<template>
    <input
        v-model="model"
        type="checkbox"
        class="size-4 rounded border-zinc-300 accent-blue-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 dark:border-zinc-700"
    />
</template>
