/**
 * Canvas API transport types — mirror the Kinevo canvas contract
 * (docs/api/openapi.yaml). These DTOs are the wire shape, distinct from the
 * framework-agnostic CanvasAdapter boundary types (CanvasScene etc.).
 */

/** Canvas metadata row (`canvases`); snake_case as served by the API. */
export interface CanvasDto {
    id: number;
    user_id: number;
    title: string;
    goal_id: number | null;
    milestone_id: number | null;
    program_id: number | null;
    task_id: number | null;
    version: number;
    archived_at: string | null;
}

/** Canvas document row (`canvas_documents`). */
export interface CanvasDocumentDto {
    id: number;
    canvas_id: number;
    schema_version: number;
    scene_json: Record<string, unknown>;
    version: number;
}

export interface CanvasListResponse {
    canvases: CanvasDto[];
}

export interface CanvasResponse {
    canvas: CanvasDto;
}

export interface CanvasDocumentResponse {
    document: CanvasDocumentDto;
}

export interface CanvasWithDocumentResponse {
    canvas: CanvasDto;
    document: CanvasDocumentDto | null;
}

export interface CanvasCreatePayload {
    title: string;
    goal_id?: number | null;
    milestone_id?: number | null;
    program_id?: number | null;
    task_id?: number | null;
    workspace_id?: number;
}

export interface CanvasSavePayload {
    scene_json: Record<string, unknown>;
    base_version: number;
}