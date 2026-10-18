<script setup>
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';
import AppButton from '../../components/AppButton.vue';
import FormField from '../../components/FormField.vue';
import TextInput from '../../components/TextInput.vue';
import AuthLayout from '../../layouts/AuthLayout.vue';

const usingRecoveryCode = ref(false);
const codeInput = ref(null);

const form = useForm({
    code: '',
    recovery_code: '',
});

async function toggleRecoveryCode() {
    usingRecoveryCode.value = !usingRecoveryCode.value;
    form.reset();
    form.clearErrors();

    await nextTick();
    codeInput.value?.$el.focus();
}

function submit() {
    form.transform((data) => (usingRecoveryCode.value ? { recovery_code: data.recovery_code } : { code: data.code })).post('/two-factor-challenge');
}
</script>

<template>
    <AuthLayout
        title="Two-factor authentication"
        :description="
            usingRecoveryCode
                ? 'Enter one of your emergency recovery codes to confirm it\'s you.'
                : 'Enter the 6-digit code from your authenticator app.'
        "
    >
        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <FormField v-if="!usingRecoveryCode" label="Authentication code" for="code" :error="form.errors.code">
                <TextInput
                    id="code"
                    ref="codeInput"
                    v-model="form.code"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    pattern="[0-9]*"
                    maxlength="6"
                    required
                    autofocus
                    :invalid="!!form.errors.code"
                />
            </FormField>

            <FormField v-else label="Recovery code" for="recovery_code" :error="form.errors.recovery_code">
                <TextInput id="recovery_code" ref="codeInput" v-model="form.recovery_code" autocomplete="off" required :invalid="!!form.errors.recovery_code" />
            </FormField>

            <AppButton type="submit" :processing="form.processing">Continue</AppButton>
        </form>

        <template #footer>
            <button type="button" class="text-blue-600 hover:underline dark:text-blue-400" @click="toggleRecoveryCode">
                {{ usingRecoveryCode ? 'Use an authentication code instead' : 'Use a recovery code instead' }}
            </button>
        </template>
    </AuthLayout>
</template>
