<script setup>
import { useForm } from '@inertiajs/vue3';
import AppButton from '../../components/AppButton.vue';
import AppCard from '../../components/AppCard.vue';
import FormField from '../../components/FormField.vue';
import TextInput from '../../components/TextInput.vue';
import SettingsLayout from '../../layouts/SettingsLayout.vue';

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.put('/user/password', {
        errorBag: 'updatePassword',
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <SettingsLayout>
        <AppCard title="Password" description="Use a long, unique password. This is separate from your Bitwarden master password.">
            <form class="flex flex-col gap-5" @submit.prevent="submit">
                <FormField label="Current password" for="current_password" :error="form.errors.current_password">
                    <TextInput
                        id="current_password"
                        v-model="form.current_password"
                        type="password"
                        autocomplete="current-password"
                        required
                        :invalid="!!form.errors.current_password"
                    />
                </FormField>

                <FormField label="New password" for="password" :error="form.errors.password">
                    <TextInput id="password" v-model="form.password" type="password" autocomplete="new-password" required :invalid="!!form.errors.password" />
                </FormField>

                <FormField label="Confirm new password" for="password_confirmation">
                    <TextInput id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" required />
                </FormField>

                <div class="flex items-center gap-4">
                    <AppButton type="submit" :processing="form.processing">Update password</AppButton>
                    <p v-if="form.recentlySuccessful" class="text-sm text-zinc-500 dark:text-zinc-400">Password updated.</p>
                </div>
            </form>
        </AppCard>
    </SettingsLayout>
</template>
