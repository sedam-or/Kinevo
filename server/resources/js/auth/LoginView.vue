<script setup lang="ts">
import { reactive, ref } from 'vue';
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
    <form class="max-w-sm mx-auto flex flex-col gap-4" @submit.prevent="submit" data-testid="login-form">
        <h1 class="text-xl font-semibold">Log in</h1>

        <div v-if="formError" class="text-sm text-[#F53003]" role="alert" data-testid="login-error">
            {{ formError }}
        </div>

        <label class="flex flex-col gap-1 text-sm">
            Email
            <input v-model="form.email" type="email" required autocomplete="email" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2" data-testid="login-email" />
            <span v-if="fieldErrors.email" class="text-[#F53003] text-xs">{{ fieldErrors.email[0] }}</span>
        </label>

        <label class="flex flex-col gap-1 text-sm">
            Password
            <input v-model="form.password" type="password" required autocomplete="current-password" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2" data-testid="login-password" />
            <span v-if="fieldErrors.password" class="text-[#F53003] text-xs">{{ fieldErrors.password[0] }}</span>
        </label>

        <button type="submit" class="border border-gray-300 dark:border-gray-600 rounded-sm px-4 py-2 font-medium" :disabled="auth.busy" data-testid="login-submit">
            {{ auth.busy ? 'Logging in…' : 'Log in' }}
        </button>

        <button type="button" class="text-sm underline" @click="props.onGoToRegister">
            First time? Register
        </button>
    </form>
</template>
