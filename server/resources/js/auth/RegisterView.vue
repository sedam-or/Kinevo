<script setup lang="ts">
import { reactive, ref } from 'vue';
import KButton from '../components/KButton.vue';
import KInput from '../components/KInput.vue';
import { useAuthStore } from './store';
import type { ApiError } from './types';

const props = defineProps<{
    onGoToLogin: () => void;
}>();

const auth = useAuthStore();

const form = reactive({
    name: '',
    email: '',
    password: '',
});

const fieldErrors = ref<Record<string, string[]>>({});
const formError = ref<string | null>(null);

async function submit(): Promise<void> {
    fieldErrors.value = {};
    formError.value = null;

    try {
        await auth.register(form.name, form.email, form.password);
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
    <form class="flex flex-col gap-5" @submit.prevent="submit" data-testid="register-form">
        <div>
            <h1 class="text-3xl font-black tracking-tight">Start your first day</h1>
            <p class="mt-1.5 text-sm text-text-muted">One workspace. Goals in, today out.</p>
        </div>

        <p
            v-if="formError"
            class="border-l-4 border-danger bg-danger-tint px-3 py-2 text-sm text-danger"
            role="alert"
            data-testid="register-error"
        >
            {{ formError }}
        </p>

        <label class="flex flex-col gap-1.5 text-sm font-semibold">
            Name
            <KInput v-model="form.name" type="text" required autocomplete="name" data-testid="register-name" />
            <span v-if="fieldErrors.name" class="font-normal text-xs text-danger">{{ fieldErrors.name[0] }}</span>
        </label>

        <label class="flex flex-col gap-1.5 text-sm font-semibold">
            Email
            <KInput v-model="form.email" type="email" required autocomplete="email" data-testid="register-email" />
            <span v-if="fieldErrors.email" class="font-normal text-xs text-danger">{{ fieldErrors.email[0] }}</span>
        </label>

        <label class="flex flex-col gap-1.5 text-sm font-semibold">
            Password
            <KInput v-model="form.password" type="password" required minlength="8" autocomplete="new-password" data-testid="register-password" />
            <span v-if="fieldErrors.password" class="font-normal text-xs text-danger">{{ fieldErrors.password[0] }}</span>
        </label>

        <KButton variant="primary" type="submit" class="w-full" :disabled="auth.busy" data-testid="register-submit">
            {{ auth.busy ? 'Creating…' : 'Create account' }}
        </KButton>

        <button
            type="button"
            class="-mt-1 self-start text-sm font-semibold underline decoration-border decoration-2 underline-offset-4 hover:text-primary"
            @click="props.onGoToLogin"
        >
            Already have an account? Log in
        </button>
    </form>
</template>
