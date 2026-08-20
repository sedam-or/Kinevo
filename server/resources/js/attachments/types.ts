export interface Attachment {
    id: number;
    task_id: number;
    filename: string;
    mime_type: string;
    size_bytes: number;
    sha256: string;
    created_at?: string | null;
}

export interface AttachmentRules {
    max_per_task: number;
    max_bytes: number;
    allowed_extensions: string[];
    allowed_mime: string[];
}

export interface AttachmentListResponse {
    attachments: Attachment[];
}

export interface AttachmentResponse {
    attachment: Attachment;
}