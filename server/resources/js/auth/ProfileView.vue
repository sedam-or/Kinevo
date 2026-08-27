<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { useAuthStore } from './store';
import { ALLOWED_LOCALES, ALLOWED_TIMEZONES, ALLOWED_WEEK_START_DAYS, type ApiError } from './types';

const auth = useAuthStore();

const form = reactive({
    displayName: '',
    locale: 'en',
    timezone: 'UTC',
    weekStartDay: 'monday',
});

const saved = ref(false);
const formError = ref<string | null>(null);

function applyProfile(): void {
    const p = auth.profile;
    if (p === null) {
        return;
    }
    form.displayName = p.display_name ?? '';
    form.locale = ALLOWED_LOCALES.includes(p.locale as (typeof ALLOWED_LOCALES)[number]) ? p.locale : 'en';
    form.timezone = ALLOWED_TIMEZONES.includes(p.timezone as (typeof ALLOWED_TIMEZONES)[number]) ? p.timezone : 'UTC';
    form.weekStartDay = ALLOWED_WEEK_START_DAYS.includes(p.week_start_day as (typeof ALLOWED_WEEK_START_DAYS)[number]) ? p.week_start_day : 'monday';
}

onMounted(async () => {
    await auth.loadProfile();
    applyProfile();
});

async function submit(): Promise<void> {
    formError.value = null;
    saved.value = false;
    try {
        await auth.updateProfile({
            display_name: form.displayName.trim() === '' ? null : form.displayName.trim(),
            locale: form.locale,
            timezone: form.timezone,
            week_start_day: form.weekStartDay,
        });
        saved.value = true;
    } catch (err) {
        formError.value = (err as ApiError).message;
    }
}
</script>

<template>
    <form class="max-w-md flex flex-col gap-4" @submit.prevent="submit" data-testid="profile-form">
        <h1 class="text-xl font-semibold">Settings</h1>

        <div v-if="saved" class="text-sm text-success" data-testid="profile-saved">
            Saved.
        </div>
        <div v-if="formError" class="text-sm text-danger" role="alert">
            {{ formError }}
        </div>

        <label class="flex flex-col gap-1 text-sm">
            Display name
            <input v-model="form.displayName" type="text" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2" data-testid="profile-display-name" />
        </label>

        <label class="flex flex-col gap-1 text-sm">
            Locale
            <select v-model="form.locale" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2" data-testid="profile-locale">
                <option v-for="l in ALLOWED_LOCALES" :key="l" :value="l">{{ l }}</option>
            </select>
        </label>

        <label class="flex flex-col gap-1 text-sm">
            Timezone
            <select v-model="form.timezone" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2" data-testid="profile-timezone">
                <option v-for="t in ALLOWED_TIMEZONES" :key="t" :value="t">{{ t }}</option>
            </select>
        </label>

        <label class="flex flex-col gap-1 text-sm">
            Week starts on
            <select v-model="form.weekStartDay" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2" data-testid="profile-week-start">
                <option v-for="d in ALLOWED_WEEK_START_DAYS" :key="d" :value="d">{{ d }}</option>
            </select>
        </label>

        <button type="submit" class="border border-gray-300 dark:border-gray-600 rounded-sm px-4 py-2 font-medium" data-testid="profile-submit">
            Save settings
        </button>
    </form>
</template>
