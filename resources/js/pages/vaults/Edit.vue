<script setup>
import { useForm } from '@inertiajs/vue3';
import VaultForm from '../../components/VaultForm.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import vaultRoutes from '../../routes/vaults';

const props = defineProps({
    vault: {
        type: Object,
        required: true,
    },
    frequencies: {
        type: Array,
        required: true,
    },
    cloudServerUrls: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    name: props.vault.name,
    server_url: props.vault.server_url,
    client_id: '',
    client_secret: '',
    master_password: '',
    formats: [...props.vault.formats],
    encrypted_json_export_password: '',
    encrypted_json_export_password_confirmation: '',
    json_export_password: '',
    json_export_password_confirmation: '',
    remove_json_export_password: false,
    frequency: props.vault.frequency,
    retain_backups_count: props.vault.retain_backups_count,
});
</script>

<template>
    <AppLayout :title="`Edit ${vault.name}`" :back="{ href: vaultRoutes.show(vault), label: vault.name }">
        <VaultForm
            :form="form"
            :vault="vault"
            :frequencies="frequencies"
            :cloud-server-urls="cloudServerUrls"
            submit-label="Save changes"
            :cancel-href="vaultRoutes.show(vault)"
            @submit="form.submit(vaultRoutes.update(vault))"
        />
    </AppLayout>
</template>
