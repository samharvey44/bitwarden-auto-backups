<script setup>
import { useForm } from '@inertiajs/vue3';
import AppButton from '../../components/AppButton.vue';
import FormField from '../../components/FormField.vue';
import TextInput from '../../components/TextInput.vue';
import AuthLayout from '../../layouts/AuthLayout.vue';
import password from '../../routes/password';

const props = defineProps({
    token: {
        type: String,
        required: true,
    },
    email: {
        type: String,
        required: true,
    },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

function submit() {
    form.submit(password.update(), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <AuthLayout title="Choose a new password">
        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <FormField label="Email address" for="email" :error="form.errors.email">
                <TextInput id="email" v-model="form.email" type="email" autocomplete="username" required :invalid="!!form.errors.email" />
            </FormField>

            <FormField label="New password" for="password" :error="form.errors.password">
                <TextInput id="password" v-model="form.password" type="password" autocomplete="new-password" required autofocus :invalid="!!form.errors.password" />
            </FormField>

            <FormField label="Confirm new password" for="password_confirmation">
                <TextInput id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" required />
            </FormField>

            <AppButton type="submit" :processing="form.processing">Reset password</AppButton>
        </form>
    </AuthLayout>
</template>
