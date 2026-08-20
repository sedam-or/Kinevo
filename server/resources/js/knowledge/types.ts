export type KnowledgeTargetType = 'goal' | 'milestone' | 'program' | 'task' | 'canvas' | 'note';

export type KnowledgeLinkType =
    | 'supports'
    | 'references'
    | 'derived_from'
    | 'evidence_for'
    | 'related_to';

export const KNOWLEDGE_TARGET_TYPES: KnowledgeTargetType[] = [
    'goal',
    'milestone',
    'program',
    'task',
    'canvas',
    'note',
];

export const KNOWLEDGE_LINK_TYPES: KnowledgeLinkType[] = [
    'supports',
    'references',
    'derived_from',
    'evidence_for',
    'related_to',
];

export interface KnowledgeLink {
    id: number;
    user_id: number;
    source_type: string;
    source_id: number;
    target_type: KnowledgeTargetType;
    target_id: number;
    link_type: KnowledgeLinkType;
    created_at?: string;
}

export interface KnowledgeLinkListResponse {
    links: KnowledgeLink[];
}

export interface KnowledgeLinkResponse {
    link: KnowledgeLink;
}

export interface CreateKnowledgeLinkPayload {
    target_type: KnowledgeTargetType;
    target_id: number;
    link_type: KnowledgeLinkType;
}

/** Context entities available as link targets (resolved by owner-scoped APIs). */
export interface LinkOption {
    type: KnowledgeTargetType;
    id: number;
    label: string;
}

export interface GoalOption {
    id: number;
    title: string;
}

export interface ProgramOption {
    id: number;
    name: string;
}

export interface TaskOption {
    id: number;
    title: string;
}

export interface MilestoneOption {
    id: number;
    goal_id: number;
    title: string;
}

export interface CanvasOption {
    id: number;
    title: string;
}

export interface NoteOption {
    id: number;
    title: string;
}
