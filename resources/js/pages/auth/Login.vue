<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';
import AppAlert from '../../components/AppAlert.vue';
import AppButton from '../../components/AppButton.vue';
import CheckboxInput from '../../components/CheckboxInput.vue';
import FormField from '../../components/FormField.vue';
import TextInput from '../../components/TextInput.vue';
import AuthLayout from '../../layouts/AuthLayout.vue';
import { register } from '../../routes';
import login from '../../routes/login';
import password from '../../routes/password';

defineProps({
    canRegister: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.submit(login.store(), {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <AuthLayout title="Log in" description="Sign in to manage your vault backups.">
        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <AppAlert v-if="page.props.status" tone="success">{{ page.props.status }}</AppAlert>

            <FormField label="Email address" for="email" :error="form.errors.email">
                <TextInput id="email" v-model="form.email" type="email" autocomplete="username" required autofocus :invalid="!!form.errors.email" />
            </FormField>

            <FormField label="Password" for="password" :error="form.errors.password">
                <template #aside>
                    <Link :href="password.request()" class="text-sm text-blue-600 hover:underline dark:text-blue-400">Forgot password?</Link>
                </template>
                <TextInput id="password" v-model="form.password" type="password" autocomplete="current-password" required :invalid="!!form.errors.password" />
            </FormField>

            <label class="flex items-center gap-2 text-sm">
                <CheckboxInput v-model="form.remember" name="remember" />
                Remember me
            </label>

            <AppButton type="submit" :processing="form.processing">Log in</AppButton>
        </form>

        <template v-if="canRegister" #footer>
            Don't have an account?
            <Link :href="register()" class="text-blue-600 hover:underline dark:text-blue-400">Create one</Link>
        </template>
    </AuthLayout>
</template>
