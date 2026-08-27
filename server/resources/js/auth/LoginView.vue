<script setup lang="ts">
import { reactive, ref } from 'vue';
import KButton from '../components/KButton.vue';
import KInput from '../components/KInput.vue';
import { useAuthStore } from './store';
import type { ApiError } from './types';

const props = defineProps<{
    onGoToRegister: () => void;
}>();

const auth = useAuthStore();

const form = reactive({
    email: '',
    password: '',
});

const fieldErrors = ref<Record<string, string[]>>({});
const formError = ref<string | null>(null);

async function submit(): Promise<void> {
    fieldErrors.value = {};
    formError.value = null;

    try {
        await auth.login(form.email, form.password);
    } catch (err) {
        const apiError = err as ApiError;
        if (apiError.errors) {
            fieldErrors.value = apiError.errors;
        } else {
            formError.value = apiError.message;
        }
    }
}
</script>

<template>
    <form class="flex flex-col gap-5" @submit.prevent="submit" data-testid="login-form">
        <div>
            <h1 class="text-3xl font-black tracking-tight">Welcome back</h1>
            <p class="mt-1.5 text-sm text-text-muted">Your day is waiting where you left it.</p>
        </div>

        <p
            v-if="formError"
            class="border-l-4 border-danger bg-danger-tint px-3 py-2 text-sm text-danger"
            role="alert"
            data-testid="login-error"
        >
            {{ formError }}
        </p>

        <label class="flex flex-col gap-1.5 text-sm font-semibold">
            Email
            <KInput v-model="form.email" type="email" required autocomplete="email" data-testid="login-email" />
            <span v-if="fieldErrors.email" class="font-normal text-xs text-danger">{{ fieldErrors.email[0] }}</span>
        </label>

        <label class="flex flex-col gap-1.5 text-sm font-semibold">
            Password
            <KInput v-model="form.password" type="password" required autocomplete="current-password" data-testid="login-password" />
            <span v-if="fieldErrors.password" class="font-normal text-xs text-danger">{{ fieldErrors.password[0] }}</span>
        </label>

        <KButton variant="primary" type="submit" class="w-full" :disabled="auth.busy" data-testid="login-submit">
            {{ auth.busy ? 'Logging in…' : 'Log in' }}
        </KButton>

        <button
            type="button"
            class="-mt-1 self-start text-sm font-semibold underline decoration-border decoration-2 underline-offset-4 hover:text-primary"
            @click="props.onGoToRegister"
        >
            First time? Register
        </button>
    </form>
</template>
