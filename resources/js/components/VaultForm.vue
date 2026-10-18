<script setup>
import { computed, ref, watch } from 'vue';
import { backupFormats } from '../lib/vaults';
import AppButton from './AppButton.vue';
import AppCard from './AppCard.vue';
import CheckboxInput from './CheckboxInput.vue';
import FormField from './FormField.vue';
import SelectInput from './SelectInput.vue';
import TextInput from './TextInput.vue';

/**
 * The settings form shared by adding and editing a vault.
 *
 * When editing, the browser never receives the vault's secrets, so each
 * secret field may be left blank to keep its current value.
 */
const props = defineProps({
    form: {
        type: Object,
        required: true,
    },
    vault: {
        type: Object,
        default: null,
    },
    frequencies: {
        type: Array,
        required: true,
    },
    cloudServerUrls: {
        type: Object,
        required: true,
    },
    submitLabel: {
        type: String,
        required: true,
    },
    cancelHref: {
        type: String,
        required: true,
    },
});

const emit = defineEmits(['submit']);

const editing = computed(() => props.vault !== null);
const hasEncryptedJsonExportPassword = computed(() => props.vault?.has_encrypted_json_export_password ?? false);
const hasJsonExportPassword = computed(() => props.vault?.has_json_export_password ?? false);

/**
 * Inputs a password manager should neither fill nor offer to save, since they
 * hold Bitwarden credentials rather than a login for this site.
 */
const secretInputAttributes = {
    autocomplete: 'off',
    'data-bwignore': 'true',
    'data-1p-ignore': 'true',
    'data-lpignore': 'true',
};

const serverChoices = [
    { value: 'us', label: 'Bitwarden cloud (US)', description: 'vault.bitwarden.com' },
    { value: 'eu', label: 'Bitwarden cloud (EU)', description: 'vault.bitwarden.eu' },
    { value: 'self-hosted', label: 'Self-hosted', description: 'Your own Bitwarden or Vaultwarden server' },
];

/**
 * Work out which server choice the form's URL belongs to. A new vault starts
 * with none chosen, so the server is always picked deliberately.
 */
function serverChoiceFor(serverUrl) {
    if (!serverUrl) {
        return null;
    }

    return Object.keys(props.cloudServerUrls).find((cloud) => props.cloudServerUrls[cloud] === serverUrl) ?? 'self-hosted';
}

const serverChoice = ref(serverChoiceFor(props.form.server_url));
const selfHostedServerUrl = ref(serverChoice.value === 'self-hosted' ? props.form.server_url : '');

watch([serverChoice, selfHostedServerUrl], ([choice, selfHostedUrl]) => {
    props.form.server_url = choice === 'self-hosted' ? selfHostedUrl : (props.cloudServerUrls[choice] ?? '');
});

const encryptedJsonEnabled = computed(() => props.form.formats.includes('encrypted_json'));
const jsonEnabled = computed(() => props.form.formats.includes('json'));

/**
 * Clear a format's password fields when it is unticked, so a half-typed
 * password for a format that is no longer chosen cannot fail validation.
 */
watch(encryptedJsonEnabled, (enabled) => {
    if (!enabled) {
        props.form.encrypted_json_export_password = '';
        props.form.encrypted_json_export_password_confirmation = '';
    }
});

watch(jsonEnabled, (enabled) => {
    if (!enabled) {
        props.form.json_export_password = '';
        props.form.json_export_password_confirmation = '';
    }
});

/**
 * Whether the JSON ZIP password fields are shown. They are hidden while an
 * existing ZIP password is being removed.
 */
const askForJsonExportPassword = computed(() => jsonEnabled.value && !props.form.remove_json_export_password);
</script>

<template>
    <form class="flex flex-col gap-6" @submit.prevent="emit('submit')">
        <AppCard title="Vault">
            <FormField label="Name" for="name" :error="form.errors.name" hint="Only used to tell your vaults apart here.">
                <TextInput id="name" v-model="form.name" required placeholder="Personal" :invalid="!!form.errors.name" />
            </FormField>
        </AppCard>

        <AppCard title="Bitwarden server" description="Where the account is hosted.">
            <fieldset class="flex flex-col gap-3">
                <legend class="sr-only">Server</legend>
                <label
                    v-for="choice in serverChoices"
                    :key="choice.value"
                    class="flex cursor-pointer items-start gap-3 rounded-md border border-zinc-200 p-3 has-checked:border-blue-500 has-checked:bg-blue-50/50 dark:border-zinc-800 dark:has-checked:border-blue-500 dark:has-checked:bg-blue-950/30"
                >
                    <input v-model="serverChoice" type="radio" name="server" :value="choice.value" class="mt-0.5 accent-blue-600" required />
                    <span class="flex flex-col">
                        <span class="text-sm font-medium">{{ choice.label }}</span>
                        <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ choice.description }}</span>
                    </span>
                </label>
            </fieldset>

            <FormField v-if="serverChoice === 'self-hosted'" label="Server URL" for="server_url" :error="form.errors.server_url">
                <TextInput
                    id="server_url"
                    v-model="selfHostedServerUrl"
                    type="url"
                    placeholder="https://vault.example.com"
                    required
                    :invalid="!!form.errors.server_url"
                />
            </FormField>
            <p v-else-if="form.errors.server_url" class="text-sm text-red-600 dark:text-red-400">{{ form.errors.server_url }}</p>
        </AppCard>

        <AppCard
            title="API key and master password"
            description="Find the API key in the Bitwarden web vault under Settings › Security › Keys › View API key."
        >
            <FormField
                label="API key client_id"
                for="client_id"
                :error="form.errors.client_id"
                :hint="editing ? 'Leave blank to keep the current value.' : 'It starts with user.'"
            >
                <TextInput
                    id="client_id"
                    v-model="form.client_id"
                    v-bind="secretInputAttributes"
                    type="password"
                    :required="!editing"
                    :invalid="!!form.errors.client_id"
                />
            </FormField>

            <FormField
                label="API key client_secret"
                for="client_secret"
                :error="form.errors.client_secret"
                :hint="editing ? 'Leave blank to keep the current value.' : null"
            >
                <TextInput
                    id="client_secret"
                    v-model="form.client_secret"
                    v-bind="secretInputAttributes"
                    type="password"
                    :required="!editing"
                    :invalid="!!form.errors.client_secret"
                />
            </FormField>

            <FormField
                label="Master password"
                for="master_password"
                :error="form.errors.master_password"
                :hint="editing ? 'Leave blank to keep the current value.' : null"
            >
                <TextInput
                    id="master_password"
                    v-model="form.master_password"
                    v-bind="secretInputAttributes"
                    type="password"
                    :required="!editing"
                    :invalid="!!form.errors.master_password"
                />
            </FormField>
        </AppCard>

        <AppCard title="Backup formats" description="Each backup is saved in every format you choose.">
            <fieldset class="flex flex-col gap-3">
                <legend class="sr-only">Formats</legend>
                <label
                    v-for="format in backupFormats"
                    :key="format.value"
                    class="flex cursor-pointer items-start gap-3 rounded-md border border-zinc-200 p-3 has-checked:border-blue-500 has-checked:bg-blue-50/50 dark:border-zinc-800 dark:has-checked:border-blue-500 dark:has-checked:bg-blue-950/30"
                >
                    <CheckboxInput v-model="form.formats" :value="format.value" class="mt-0.5" />
                    <span class="flex flex-col">
                        <span class="text-sm font-medium">{{ format.label }}</span>
                        <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ format.description }}</span>
                    </span>
                </label>
                <p v-if="form.errors.formats" class="text-sm text-red-600 dark:text-red-400">{{ form.errors.formats }}</p>
            </fieldset>

            <div v-if="encryptedJsonEnabled" class="flex flex-col gap-4 border-t border-zinc-200 pt-5 dark:border-zinc-800">
                <h3 class="text-sm font-semibold">Encrypted JSON</h3>
                <FormField
                    :label="hasEncryptedJsonExportPassword ? 'New export password' : 'Export password'"
                    for="encrypted_json_export_password"
                    :error="form.errors.encrypted_json_export_password"
                    :hint="
                        hasEncryptedJsonExportPassword
                            ? 'Leave blank to keep the current password.'
                            : 'Needed to import the backup. Keep a copy somewhere other than this vault.'
                    "
                >
                    <TextInput
                        id="encrypted_json_export_password"
                        v-model="form.encrypted_json_export_password"
                        v-bind="secretInputAttributes"
                        type="password"
                        :required="!hasEncryptedJsonExportPassword"
                        :invalid="!!form.errors.encrypted_json_export_password"
                    />
                </FormField>
                <FormField
                    v-if="!hasEncryptedJsonExportPassword || form.encrypted_json_export_password"
                    label="Confirm export password"
                    for="encrypted_json_export_password_confirmation"
                >
                    <TextInput
                        id="encrypted_json_export_password_confirmation"
                        v-model="form.encrypted_json_export_password_confirmation"
                        v-bind="secretInputAttributes"
                        type="password"
                        required
                    />
                </FormField>
            </div>

            <div v-if="jsonEnabled" class="flex flex-col gap-4 border-t border-zinc-200 pt-5 dark:border-zinc-800">
                <h3 class="text-sm font-semibold">JSON</h3>

                <label v-if="hasJsonExportPassword" class="flex items-center gap-2 text-sm">
                    <CheckboxInput v-model="form.remove_json_export_password" />
                    Remove the ZIP password and save plain, unencrypted JSON
                </label>

                <template v-if="askForJsonExportPassword">
                    <FormField
                        :label="hasJsonExportPassword ? 'New ZIP password' : 'ZIP password'"
                        for="json_export_password"
                        :error="form.errors.json_export_password"
                        :hint="hasJsonExportPassword ? 'Leave blank to keep the current password.' : 'Leave blank to save plain, unencrypted JSON.'"
                    >
                        <TextInput
                            id="json_export_password"
                            v-model="form.json_export_password"
                            v-bind="secretInputAttributes"
                            type="password"
                            :invalid="!!form.errors.json_export_password"
                        />
                    </FormField>
                    <FormField v-if="form.json_export_password" label="Confirm ZIP password" for="json_export_password_confirmation">
                        <TextInput
                            id="json_export_password_confirmation"
                            v-model="form.json_export_password_confirmation"
                            v-bind="secretInputAttributes"
                            type="password"
                            required
                        />
                    </FormField>
                </template>
            </div>
        </AppCard>

        <AppCard title="Schedule">
            <div class="grid gap-5 sm:grid-cols-2">
                <FormField label="Back up" for="frequency" :error="form.errors.frequency">
                    <SelectInput id="frequency" v-model="form.frequency" :options="frequencies" :invalid="!!form.errors.frequency" />
                </FormField>

                <FormField
                    label="Backups to keep"
                    for="retain_backups_count"
                    :error="form.errors.retain_backups_count"
                    hint="Older backups and their files are deleted after each successful backup."
                >
                    <TextInput
                        id="retain_backups_count"
                        v-model.number="form.retain_backups_count"
                        type="number"
                        min="1"
                        max="65535"
                        required
                        :invalid="!!form.errors.retain_backups_count"
                    />
                </FormField>
            </div>
        </AppCard>

        <div class="flex items-center justify-end gap-3">
            <AppButton variant="ghost" :href="cancelHref">Cancel</AppButton>
            <AppButton type="submit" :processing="form.processing">{{ submitLabel }}</AppButton>
        </div>
    </form>
</template>
