<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import AppButton from '../../components/AppButton.vue';
import AppCard from '../../components/AppCard.vue';
import FormField from '../../components/FormField.vue';
import TextInput from '../../components/TextInput.vue';
import SettingsLayout from '../../layouts/SettingsLayout.vue';
import userProfileInformation from '../../routes/user-profile-information';

const page = usePage();

const form = useForm({
    name: page.props.auth.user.name,
    email: page.props.auth.user.email,
});

function submit() {
    form.submit(userProfileInformation.update(), {
        errorBag: 'updateProfileInformation',
        preserveScroll: true,
    });
}
</script>

<template>
    <SettingsLayout>
        <AppCard title="Profile" description="Your name and the email address you log in with.">
            <form class="flex flex-col gap-5" @submit.prevent="submit">
                <FormField label="Name" for="name" :error="form.errors.name">
                    <TextInput id="name" v-model="form.name" autocomplete="name" required :invalid="!!form.errors.name" />
                </FormField>

                <FormField label="Email address" for="email" :error="form.errors.email">
                    <TextInput id="email" v-model="form.email" type="email" autocomplete="username" required :invalid="!!form.errors.email" />
                </FormField>

                <div class="flex items-center gap-4">
                    <AppButton type="submit" :processing="form.processing">Save</AppButton>
                    <p v-if="form.recentlySuccessful" class="text-sm text-zinc-500 dark:text-zinc-400">Saved.</p>
                </div>
            </form>
        </AppCard>
    </SettingsLayout>
</template>
