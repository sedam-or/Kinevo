<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { attachmentApi } from './api';
import type { Attachment, AttachmentRules } from './types';
import type { ApiError } from '../api/types';

const props = defineProps<{
    taskId: number;
    completed: boolean;
}>();

const attachments = ref<Attachment[]>([]);
const rules = ref<AttachmentRules | null>(null);
const loading = ref(false);
const uploading = ref(false);
const error = ref<ApiError | null>(null);
const uploadError = ref<string | null>(null);

async function loadRules(): Promise<void> {
    try {
        rules.value = await attachmentApi.rules();
    } catch {
        rules.value = null;
    }
}

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;
    try {
        attachments.value = (await attachmentApi.list(props.taskId)).attachments;
    } catch (err) {
        error.value = err as ApiError;
    } finally {
        loading.value = false;
    }
}

onMounted(async () => {
    await loadRules();
    await load();
});

function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }
    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

async function onFileSelected(event: Event): Promise<void> {
    uploadError.value = null;
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) {
        return;
    }
    input.value = '';

    if (rules.value && attachments.value.length >= rules.value.max_per_task) {
        uploadError.value = `A task can have at most ${rules.value.max_per_task} attachments.`;
        return;
    }
    if (rules.value && file.size > rules.value.max_bytes) {
        uploadError.value = 'Attachment exceeds the 5 MB size limit.';
        return;
    }

    uploading.value = true;
    try {
        await attachmentApi.upload(props.taskId, file);
        await load();
    } catch (err) {
        const apiError = err as ApiError;
        uploadError.value = apiError.message ?? 'Upload failed.';
    } finally {
        uploading.value = false;
    }
}

async function remove(attachment: Attachment): Promise<void> {
    try {
        await attachmentApi.remove(props.taskId, attachment.id);
        await load();
    } catch (err) {
        error.value = err as ApiError;
    }
}

function download(attachment: Attachment): void {
    void attachmentApi.download(props.taskId, attachment.id, attachment.filename).catch((err) => {
        uploadError.value = err instanceof Error ? err.message : 'Download failed.';
    });
}

function allowedHint(): string {
    const r = rules.value;
    if (!r) {
        return 'JPG, PNG, PDF · max 5 MB';
    }
    return `${r.allowed_extensions.join(', ').toUpperCase()} · max ${formatBytes(r.max_bytes)}`;
}
</script>

<template>
    <div class="mt-4 border border-gray-300 dark:border-gray-700 rounded-sm p-3" data-testid="attachments">
        <div class="flex items-center justify-between mb-2">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400">Evidence attachments</div>
            <span v-if="rules" class="text-xs text-gray-400">{{ attachments.length }}/{{ rules.max_per_task }}</span>
        </div>

        <div v-if="loading" class="text-sm text-gray-500 dark:text-gray-400" data-testid="attachments-loading">
            Loading…
        </div>

        <div v-else-if="error" class="text-sm text-[#F53003]" role="alert" data-testid="attachments-error">
            {{ error.message }}
        </div>

        <template v-else>
            <ul v-if="attachments.length > 0" class="space-y-1">
                <li v-for="attachment in attachments" :key="attachment.id" class="flex items-center justify-between gap-2 text-sm" data-testid="attachment-item">
                    <div class="min-w-0">
                        <div class="truncate text-gray-700 dark:text-gray-300">{{ attachment.filename }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ formatBytes(attachment.size_bytes) }} · {{ attachment.mime_type }}</div>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <button
                            type="button"
                            class="border border-gray-300 dark:border-gray-600 rounded-sm px-2 py-0.5 text-xs"
                            data-testid="attachment-download"
                            @click="download(attachment)"
                        >
                            Download
                        </button>
                        <button
                            type="button"
                            class="border border-gray-300 dark:border-gray-600 rounded-sm px-2 py-0.5 text-xs"
                            data-testid="attachment-delete"
                            @click="remove(attachment)"
                        >
                            Delete
                        </button>
                    </div>
                </li>
            </ul>
            <p v-else class="text-sm text-gray-500 dark:text-gray-400" data-testid="attachments-empty">
                No attachments yet.
            </p>

            <div v-if="uploadError" class="mt-2 text-sm text-[#F53003]" role="alert" data-testid="attachments-upload-error">
                {{ uploadError }}
            </div>

            <div v-if="props.completed" class="mt-3">
                <label
                    class="inline-block border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1 text-sm cursor-pointer disabled:opacity-50"
                    :class="uploading ? 'opacity-50' : ''"
                >
                    {{ uploading ? 'Uploading…' : 'Add attachment' }}
                    <input
                        type="file"
                        class="hidden"
                        :accept="rules ? rules.allowed_extensions.map((e) => `.${e}`).join(',') : '.jpg,.jpeg,.png,.pdf'"
                        :disabled="uploading"
                        data-testid="attachment-file-input"
                        @change="onFileSelected"
                    />
                </label>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ allowedHint() }}</p>
            </div>
            <p v-else class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                Attachments can be added once the task is completed.
            </p>
        </template>
    </div>
</template>