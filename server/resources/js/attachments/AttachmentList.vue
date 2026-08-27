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
    <div class="surface-supporting mt-4" data-testid="attachments">
        <div class="flex items-center justify-between mb-2">
            <div class="text-xs uppercase text-text-muted">Evidence attachments</div>
            <span v-if="rules" class="text-xs text-text-muted">{{ attachments.length }}/{{ rules.max_per_task }}</span>
        </div>

        <div v-if="loading" class="text-sm text-text-muted" data-testid="attachments-loading">
            Loading…
        </div>

        <div v-else-if="error" class="text-sm text-danger" role="alert" data-testid="attachments-error">
            {{ error.message }}
        </div>

        <template v-else>
            <ul v-if="attachments.length > 0" class="space-y-2">
                <li
                    v-for="attachment in attachments"
                    :key="attachment.id"
                    class="surface-metadata flex items-center justify-between gap-2 border-b border-border/20 pb-2 last:border-b-0 last:pb-0 text-sm"
                    data-testid="attachment-item"
                >
                    <div class="min-w-0">
                        <div class="truncate font-medium">{{ attachment.filename }}</div>
                        <div class="text-xs text-text-muted">{{ formatBytes(attachment.size_bytes) }} · {{ attachment.mime_type }}</div>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <KButton variant="ghost" data-testid="attachment-download" @click="download(attachment)">
                            Download
                        </KButton>
                        <KButton variant="ghost" data-testid="attachment-delete" @click="remove(attachment)">
                            Delete
                        </KButton>
                    </div>
                </li>
            </ul>
            <p v-else class="text-sm text-text-muted" data-testid="attachments-empty">
                No attachments yet.
            </p>

            <div v-if="uploadError" class="mt-2 text-sm text-danger" role="alert" data-testid="attachments-upload-error">
                {{ uploadError }}
            </div>

            <!-- Upload row (L3 secondary container; opacity tracks the input's
                 disabled state — no dead disabled:* on the label) -->
            <div v-if="props.completed" class="surface-secondary p-3 mt-3 flex flex-col gap-2">
                <label
                    class="inline-flex items-center gap-1.5 border-2 border-border bg-bg rounded-sm px-4 py-2 min-h-[44px] text-sm cursor-pointer shadow-rest hover:shadow-hover active:translate-x-0.5 active:translate-y-0.5 active:shadow-active"
                    :class="{ 'opacity-50': uploading }"
                >
                    <KIcon name="plus" :size="16" />
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
                <p class="text-xs text-text-muted">{{ allowedHint() }}</p>
            </div>
            <p v-else class="mt-2 text-xs text-text-muted">
                Attachments can be added once the task is completed.
            </p>
        </template>
    </div>
</template>