<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';
import AppAlert from '../../components/AppAlert.vue';
import AppButton from '../../components/AppButton.vue';
import FormField from '../../components/FormField.vue';
import TextInput from '../../components/TextInput.vue';
import AuthLayout from '../../layouts/AuthLayout.vue';
import { login } from '../../routes';
import password from '../../routes/password';

const page = usePage();

const form = useForm({
    email: '',
});
</script>

<template>
    <AuthLayout title="Forgot your password?" description="Enter your email address and we'll send you a link to choose a new one.">
        <form class="flex flex-col gap-5" @submit.prevent="form.submit(password.email())">
            <AppAlert v-if="page.props.status" tone="success">{{ page.props.status }}</AppAlert>

            <FormField label="Email address" for="email" :error="form.errors.email">
                <TextInput id="email" v-model="form.email" type="email" autocomplete="username" required autofocus :invalid="!!form.errors.email" />
            </FormField>

            <AppButton type="submit" :processing="form.processing">Email reset link</AppButton>
        </form>

        <template #footer>
            <Link :href="login()" class="text-blue-600 hover:underline dark:text-blue-400">Back to log in</Link>
        </template>
    </AuthLayout>
</template>
