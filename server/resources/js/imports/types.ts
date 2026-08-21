export interface KrsImportRow {
    day: string;
    start_time: string;
    end_time: string;
    course: string;
    location?: string | null;
}

export interface KrsImportReportItem {
    line?: string;
    course?: string | null;
    error?: string;
    warning?: string;
}

export interface KrsImport {
    id: number;
    filename: string;
    status: 'pending' | 'confirmed' | 'discarded';
    confidence: number | null;
    rows: KrsImportRow[];
    errors: KrsImportReportItem[];
    warnings: KrsImportReportItem[];
    created_at?: string | null;
}

export interface KrsImportResponse {
    import: KrsImport;
}

export interface IcsImportRow {
    uid: string | null;
    summary: string;
    location?: string | null;
    start_at: string;
    end_at: string;
    type: 'one_time' | 'recurring';
    recurrence?: string | null;
    tzid: string;
    conflict: boolean;
    conflict_with?: string | null;
}

export interface IcsImportReportItem {
    index: number;
    summary: string | null;
    error?: string;
    warning?: string;
}

export interface IcsImport {
    id: number;
    filename: string;
    status: 'pending' | 'confirmed' | 'discarded';
    confidence: number | null;
    rows: IcsImportRow[];
    errors: IcsImportReportItem[];
    warnings: IcsImportReportItem[];
    created_at?: string | null;
}

export interface IcsImportResponse {
    import: IcsImport;
}