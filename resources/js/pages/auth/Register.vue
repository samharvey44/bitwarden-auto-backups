<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppButton from '../../components/AppButton.vue';
import FormField from '../../components/FormField.vue';
import TextInput from '../../components/TextInput.vue';
import AuthLayout from '../../layouts/AuthLayout.vue';
import { login } from '../../routes';
import registration from '../../routes/register';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.submit(registration.store(), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <AuthLayout title="Create an account" description="Set up an account to start backing up your vaults.">
        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <FormField label="Name" for="name" :error="form.errors.name">
                <TextInput id="name" v-model="form.name" autocomplete="name" required autofocus :invalid="!!form.errors.name" />
            </FormField>

            <FormField label="Email address" for="email" :error="form.errors.email">
                <TextInput id="email" v-model="form.email" type="email" autocomplete="username" required :invalid="!!form.errors.email" />
            </FormField>

            <FormField label="Password" for="password" :error="form.errors.password">
                <TextInput id="password" v-model="form.password" type="password" autocomplete="new-password" required :invalid="!!form.errors.password" />
            </FormField>

            <FormField label="Confirm password" for="password_confirmation">
                <TextInput id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" required />
            </FormField>

            <AppButton type="submit" :processing="form.processing">Create account</AppButton>
        </form>

        <template #footer>
            Already have an account?
            <Link :href="login()" class="text-blue-600 hover:underline dark:text-blue-400">Log in</Link>
        </template>
    </AuthLayout>
</template>
