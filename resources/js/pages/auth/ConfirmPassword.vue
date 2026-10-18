<script setup>
import { useForm } from '@inertiajs/vue3';
import AppButton from '../../components/AppButton.vue';
import FormField from '../../components/FormField.vue';
import TextInput from '../../components/TextInput.vue';
import AuthLayout from '../../layouts/AuthLayout.vue';

const form = useForm({
    password: '',
});

function submit() {
    form.post('/user/confirm-password', {
        onFinish: () => form.reset(),
    });
}
</script>

<template>
    <AuthLayout title="Confirm your password" description="This is a sensitive area. Please confirm your password to continue.">
        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <FormField label="Password" for="password" :error="form.errors.password">
                <TextInput id="password" v-model="form.password" type="password" autocomplete="current-password" required autofocus :invalid="!!form.errors.password" />
            </FormField>

            <AppButton type="submit" :processing="form.processing">Confirm</AppButton>
        </form>
    </AuthLayout>
</template>
