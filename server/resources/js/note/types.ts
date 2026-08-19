export interface Note {
    id: number;
    user_id: number;
    title: string;
    document_json: Record<string, unknown> | null;
    markdown_cache: string | null;
    plain_text_cache: string | null;
    version: number;
    created_at?: string;
    updated_at?: string;
}

export interface NoteListResponse {
    notes: Note[];
}

export interface NoteResponse {
    note: Note;
}

export interface SearchResponse {
    notes: Note[];
    query: string;
}

export interface CreateNotePayload {
    title: string;
    document_json?: Record<string, unknown> | null;
    markdown_cache?: string | null;
    plain_text_cache?: string | null;
}

export interface UpdateNotePayload {
    title?: string;
    document_json?: Record<string, unknown> | null;
    markdown_cache?: string | null;
    plain_text_cache?: string | null;
    base_version: number;
}

export interface NoteLink {
    id: number;
    user_id: number;
    source_type: string;
    source_id: number;
    target_type: string;
    target_id: number;
    link_type: string;
    created_at?: string;
}

export interface NoteLinksResponse {
    links: NoteLink[];
}
