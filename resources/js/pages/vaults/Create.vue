<script setup>
import { useForm } from '@inertiajs/vue3';
import VaultForm from '../../components/VaultForm.vue';
import AppLayout from '../../layouts/AppLayout.vue';

defineProps({
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
    name: '',
    server_url: '',
    client_id: '',
    client_secret: '',
    master_password: '',
    formats: ['encrypted_json'],
    encrypted_json_export_password: '',
    encrypted_json_export_password_confirmation: '',
    json_export_password: '',
    json_export_password_confirmation: '',
    frequency: 'daily',
    retain_backups_count: 30,
});
</script>

<template>
    <AppLayout title="Add a vault" description="Back up a Bitwarden account on a schedule.">
        <VaultForm
            :form="form"
            :frequencies="frequencies"
            :cloud-server-urls="cloudServerUrls"
            submit-label="Add vault"
            cancel-href="/vaults"
            @submit="form.post('/vaults')"
        />
    </AppLayout>
</template>
