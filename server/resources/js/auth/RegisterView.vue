<script setup lang="ts">
import { reactive, ref } from 'vue';
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
    <form class="max-w-sm mx-auto flex flex-col gap-4" @submit.prevent="submit" data-testid="register-form">
        <h1 class="text-xl font-semibold">Create your Kinevo account</h1>

        <div v-if="formError" class="text-sm text-[#F53003]" role="alert" data-testid="register-error">
            {{ formError }}
        </div>

        <label class="flex flex-col gap-1 text-sm">
            Name
            <input v-model="form.name" type="text" required class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2" data-testid="register-name" />
            <span v-if="fieldErrors.name" class="text-[#F53003] text-xs">{{ fieldErrors.name[0] }}</span>
        </label>

        <label class="flex flex-col gap-1 text-sm">
            Email
            <input v-model="form.email" type="email" required class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2" data-testid="register-email" />
            <span v-if="fieldErrors.email" class="text-[#F53003] text-xs">{{ fieldErrors.email[0] }}</span>
        </label>

        <label class="flex flex-col gap-1 text-sm">
            Password
            <input v-model="form.password" type="password" required minlength="8" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2" data-testid="register-password" />
            <span v-if="fieldErrors.password" class="text-[#F53003] text-xs">{{ fieldErrors.password[0] }}</span>
        </label>

        <button type="submit" class="border border-gray-300 dark:border-gray-600 rounded-sm px-4 py-2 font-medium" :disabled="auth.busy" data-testid="register-submit">
            {{ auth.busy ? 'Creating…' : 'Create account' }}
        </button>

        <button type="button" class="text-sm underline" @click="props.onGoToLogin">
            Already have an account? Log in
        </button>
    </form>
</template>
