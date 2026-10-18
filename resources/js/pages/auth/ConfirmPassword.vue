<script setup>
import { router, useForm } from '@inertiajs/vue3';
import AppButton from '../../components/AppButton.vue';
import FormField from '../../components/FormField.vue';
import TextInput from '../../components/TextInput.vue';
import AuthLayout from '../../layouts/AuthLayout.vue';
import passwordConfirmation from '../../routes/password/confirm';
import settings from '../../routes/settings';

const form = useForm({
    password: '',
});

/**
 * Return to the page that asked for the password, or to settings when this
 * page was opened directly.
 */
function goBack() {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        router.visit(settings.profile());
    }
}

function submit() {
    form.submit(passwordConfirmation.store(), {
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

        <template #footer>
            <button type="button" class="text-blue-600 hover:underline dark:text-blue-400" @click="goBack">Go back</button>
        </template>
    </AuthLayout>
</template>
