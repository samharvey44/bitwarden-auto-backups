<script setup>
import { Link, useForm, usePoll } from '@inertiajs/vue3';
import { watch } from 'vue';
import { formatBytes } from '../lib/formatBytes';
import { formatDateTime } from '../lib/formatDateTime';
import { nextBackupLabel } from '../lib/vaults';
import backupRoutes from '../routes/vaults/backups';
import AppButton from './AppButton.vue';
import AppCard from './AppCard.vue';
import BackupStatusBadge from './BackupStatusBadge.vue';

const props = defineProps({
    vault: {
        type: Object,
        required: true,
    },
    backups: {
        type: Object,
        required: true,
    },
    backupInProgress: {
        type: Boolean,
        required: true,
    },
});

const backupForm = useForm({});

const { start, stop } = usePoll(
    3000,
    { only: ['vault', 'backups', 'backupInProgress', 'backupFileCount'] },
    { autoStart: props.backupInProgress, mode: 'rest', background: 'pause' },
);

watch(
    () => props.backupInProgress,
    (inProgress) => (inProgress ? start() : stop()),
);

function backUpNow() {
    backupForm.submit(backupRoutes.store(props.vault), { preserveScroll: true });
}

/**
 * Name a file by its format, noting when JSON is inside a password-protected ZIP.
 */
function fileLabel(file) {
    return file.format === 'json' && file.is_encrypted ? 'JSON (ZIP)' : file.format_label;
}

/**
 * Describe when the backup reached its current status.
 */
function when(backup) {
    switch (backup.status) {
        case 'pending':
            return `Queued ${formatDateTime(backup.queued_at)}`;
        case 'running':
            return `Started ${formatDateTime(backup.started_at)}`;
        default:
            return formatDateTime(backup.completed_at ?? backup.queued_at);
    }
}
</script>

<template>
    <AppCard title="Backups" :description="`The last ${vault.retain_backups_count} successful backups are kept.`" flush>
        <template #actions>
            <AppButton :processing="backupForm.processing" :disabled="backupInProgress || vault.formats.length === 0" @click="backUpNow">
                {{ backupInProgress ? 'Backing up…' : 'Back up now' }}
            </AppButton>
        </template>

        <p v-if="backupForm.errors.backup" class="border-b border-zinc-200 px-6 py-3 text-sm text-red-600 dark:border-zinc-800 dark:text-red-400">
            {{ backupForm.errors.backup }}
        </p>

        <p v-if="!backups.data.length" class="px-6 py-5 text-sm text-zinc-500 dark:text-zinc-400">
            No backups yet. Next scheduled backup: {{ nextBackupLabel(vault) }}.
        </p>

        <ul v-else class="divide-y divide-zinc-200 dark:divide-zinc-800">
            <li v-for="backup in backups.data" :key="backup.id" class="flex flex-col gap-3 px-6 py-4">
                <div class="flex flex-wrap items-center gap-3">
                    <BackupStatusBadge :status="backup.status" :label="backup.status_label" />
                    <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ when(backup) }}</span>
                </div>

                <p v-if="backup.error" class="text-sm break-words text-red-600 dark:text-red-400">{{ backup.error }}</p>

                <div v-if="backup.files.length" class="flex flex-wrap gap-2">
                    <a
                        v-for="file in backup.files"
                        :key="file.id"
                        :href="file.download_url"
                        download
                        class="inline-flex items-center gap-2 rounded-md border border-zinc-200 px-3 py-1.5 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                    >
                        <svg class="size-4 text-zinc-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 4v11m0 0-4-4m4 4 4-4M5 20h14" />
                        </svg>
                        <span class="font-medium">{{ fileLabel(file) }}</span>
                        <span class="text-zinc-500 dark:text-zinc-400">{{ formatBytes(file.size_bytes) }}</span>
                        <span v-if="!file.is_encrypted" class="rounded bg-amber-100 px-1.5 text-xs text-amber-800 dark:bg-amber-950 dark:text-amber-300">Unencrypted</span>
                    </a>
                </div>
            </li>
        </ul>

        <nav v-if="backups.last_page > 1" class="flex items-center justify-between border-t border-zinc-200 px-6 py-3 text-sm dark:border-zinc-800">
            <span class="text-zinc-500 dark:text-zinc-400">Page {{ backups.current_page }} of {{ backups.last_page }}</span>
            <div class="flex gap-2">
                <AppButton v-if="backups.prev_page_url" variant="secondary" :href="backups.prev_page_url" preserve-scroll :only="['backups']">Newer</AppButton>
                <AppButton v-if="backups.next_page_url" variant="secondary" :href="backups.next_page_url" preserve-scroll :only="['backups']">Older</AppButton>
            </div>
        </nav>
    </AppCard>
</template>
