<script setup>
import { ref, watch } from 'vue';

const open = defineModel('open', { type: Boolean, default: false });

defineProps({
    title: {
        type: String,
        required: true,
    },
});

const dialog = ref(null);

watch(open, (isOpen) => (isOpen ? dialog.value.showModal() : dialog.value.close()));
</script>

<template>
    <dialog
        ref="dialog"
        class="m-auto w-full max-w-md rounded-xl border border-zinc-200 bg-white p-0 text-zinc-900 shadow-xl backdrop:bg-zinc-950/50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100"
        @close="open = false"
    >
        <div class="flex flex-col gap-4 p-6">
            <h2 class="text-lg font-semibold">{{ title }}</h2>
            <div class="flex flex-col gap-4 text-sm text-zinc-600 dark:text-zinc-400">
                <slot />
            </div>
        </div>
        <div class="flex justify-end gap-3 border-t border-zinc-200 bg-zinc-50 px-6 py-4 dark:border-zinc-800 dark:bg-zinc-950/40">
            <slot name="actions" />
        </div>
    </dialog>
</template>
