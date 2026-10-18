<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppButton from '../../components/AppButton.vue';
import AppCard from '../../components/AppCard.vue';
import CheckboxInput from '../../components/CheckboxInput.vue';
import ConfirmDialog from '../../components/ConfirmDialog.vue';
import { formatDateTime } from '../../lib/formatDateTime';
import { formatLabels, nextBackupLabel } from '../../lib/vaults';
import AppLayout from '../../layouts/AppLayout.vue';

const props = defineProps({
    vault: {
        type: Object,
        required: true,
    },
    backupFileCount: {
        type: Number,
        required: true,
    },
});

const verifyForm = useForm({});
const deleteForm = useForm({ keep_files: false });
const confirmingDelete = ref(false);

const details = computed(() => [
    { label: 'Server', value: props.vault.server_label },
    { label: 'Formats', value: formatLabels(props.vault).join(', ') || 'None, so the vault is paused' },
    { label: 'Schedule', value: `${props.vault.frequency_label}, keeping the last ${props.vault.retain_backups_count} backups` },
    { label: 'Last backup', value: formatDateTime(props.vault.last_backed_up_at) ?? 'Never' },
    { label: 'Next backup', value: nextBackupLabel(props.vault) },
]);

function verify() {
    verifyForm.post(`/vaults/${props.vault.id}/verify`, { preserveScroll: true });
}

function destroy() {
    deleteForm.delete(`/vaults/${props.vault.id}`);
}
</script>

<template>
    <AppLayout :title="vault.name">
        <template #actions>
            <div class="flex flex-wrap gap-3">
                <AppButton variant="secondary" :processing="verifyForm.processing" @click="verify">
                    {{ verifyForm.processing ? 'Checking credentials…' : 'Check credentials' }}
                </AppButton>
                <AppButton variant="secondary" :href="`/vaults/${vault.id}/edit`">Edit</AppButton>
            </div>
        </template>

        <AppCard title="Settings">
            <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-[10rem_1fr]">
                <template v-for="detail in details" :key="detail.label">
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ detail.label }}</dt>
                    <dd>{{ detail.value }}</dd>
                </template>
            </dl>
        </AppCard>

        <AppCard title="Delete vault" description="Removes the vault, its credentials and its backup history.">
            <div>
                <AppButton variant="danger" @click="confirmingDelete = true">Delete vault</AppButton>
            </div>
        </AppCard>

        <ConfirmDialog v-model:open="confirmingDelete" :title="`Delete ${vault.name}?`">
            <p>The vault's credentials, settings and backup history will be deleted. This can't be undone.</p>

            <label v-if="backupFileCount" class="flex items-start gap-2 text-zinc-900 dark:text-zinc-100">
                <CheckboxInput v-model="deleteForm.keep_files" class="mt-0.5" />
                <span>
                    Keep its {{ backupFileCount }} backup {{ backupFileCount === 1 ? 'file' : 'files' }} on disk
                    <span class="block text-zinc-500 dark:text-zinc-400">The passwords stored for them will still be deleted.</span>
                </span>
            </label>

            <template #actions>
                <AppButton variant="ghost" @click="confirmingDelete = false">Cancel</AppButton>
                <AppButton variant="danger" :processing="deleteForm.processing" @click="destroy">Delete vault</AppButton>
            </template>
        </ConfirmDialog>
    </AppLayout>
</template>
