export interface KrsImportRow {
    day: string;
    start_time: string;
    end_time: string;
    course: string;
    location?: string | null;
}

export interface KrsImport {
    id: number;
    filename: string;
    status: 'pending' | 'confirmed' | 'discarded';
    confidence: number | null;
    rows: KrsImportRow[];
    created_at?: string | null;
}

export interface KrsImportResponse {
    import: KrsImport;
}