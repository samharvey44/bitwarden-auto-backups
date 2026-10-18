<script setup>
import { Link } from '@inertiajs/vue3';
import AppButton from '../../components/AppButton.vue';
import { formatDateTime } from '../../lib/formatDateTime';
import { formatLabels, nextBackupLabel } from '../../lib/vaults';
import AppLayout from '../../layouts/AppLayout.vue';

defineProps({
    vaults: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <AppLayout title="Vaults">
        <template v-if="vaults.length" #actions>
            <AppButton href="/vaults/create">Add vault</AppButton>
        </template>

        <div
            v-if="!vaults.length"
            class="flex flex-col items-center gap-4 rounded-xl border border-dashed border-zinc-300 px-6 py-16 text-center dark:border-zinc-700"
        >
            <div class="flex flex-col gap-1">
                <h2 class="font-semibold">No vaults yet</h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Add a Bitwarden account to start backing it up on a schedule.</p>
            </div>
            <AppButton href="/vaults/create">Add your first vault</AppButton>
        </div>

        <ul
            v-else
            class="divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs dark:divide-zinc-800 dark:border-zinc-800 dark:bg-zinc-900"
        >
            <li v-for="vault in vaults" :key="vault.id">
                <Link
                    :href="`/vaults/${vault.id}`"
                    class="grid gap-3 px-5 py-4 hover:bg-zinc-50 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)] sm:items-center dark:hover:bg-zinc-800/50"
                >
                    <div class="flex min-w-0 flex-col gap-1">
                        <span class="truncate font-medium">{{ vault.name }}</span>
                        <span class="truncate text-sm text-zinc-500 dark:text-zinc-400">
                            {{ vault.server_label }} · {{ formatLabels(vault).join(', ') || 'No formats' }}
                        </span>
                    </div>

                    <div class="flex flex-col text-sm">
                        <span class="text-zinc-500 dark:text-zinc-400">Last backup</span>
                        <span>{{ formatDateTime(vault.last_backed_up_at) ?? 'Never' }}</span>
                    </div>

                    <div class="flex flex-col text-sm">
                        <span class="text-zinc-500 dark:text-zinc-400">Next backup · {{ vault.frequency_label }}</span>
                        <span>{{ nextBackupLabel(vault) }}</span>
                    </div>
                </Link>
            </li>
        </ul>
    </AppLayout>
</template>
