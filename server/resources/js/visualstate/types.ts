export type VisualStateValue =
    | 'locked'
    | 'conflict'
    | 'overdue'
    | 'draft'
    | 'proposed'
    | 'offline'
    | 'syncing'
    | 'queued'
    | 'retrying'
    | 'failed'
    | 'saved'
    | 'online';

export interface VisualStateDefinition {
    value: VisualStateValue;
    label: string;
    /** Non-color signal: an icon/symbol shown alongside text. */
    glyph: string;
    /** Non-color signal: whether the badge uses a dashed border pattern. */
    dashed: boolean;
    /** Semantic tone for color where available (never the only signal). */
    tone: 'neutral' | 'danger' | 'warning' | 'info' | 'success';
}

export const VISUAL_STATES: Record<VisualStateValue, VisualStateDefinition> = {
    locked: { value: 'locked', label: 'Locked', glyph: '🔒', dashed: false, tone: 'info' },
    conflict: { value: 'conflict', label: 'Conflict', glyph: '!', dashed: true, tone: 'danger' },
    overdue: { value: 'overdue', label: 'Overdue', glyph: '⏰', dashed: true, tone: 'danger' },
    draft: { value: 'draft', label: 'Draft', glyph: '✎', dashed: true, tone: 'neutral' },
    proposed: { value: 'proposed', label: 'Proposed', glyph: '→', dashed: true, tone: 'warning' },
    offline: { value: 'offline', label: 'Offline', glyph: '✕', dashed: true, tone: 'neutral' },
    syncing: { value: 'syncing', label: 'Syncing', glyph: '↻', dashed: false, tone: 'info' },
    queued: { value: 'queued', label: 'Queued', glyph: '⇢', dashed: true, tone: 'warning' },
    retrying: { value: 'retrying', label: 'Retrying', glyph: '⟳', dashed: true, tone: 'warning' },
    failed: { value: 'failed', label: 'Failed', glyph: '×', dashed: true, tone: 'danger' },
    saved: { value: 'saved', label: 'Saved', glyph: '✓', dashed: false, tone: 'success' },
    online: { value: 'online', label: 'Online', glyph: '●', dashed: false, tone: 'success' },
};

export function visualState(value: VisualStateValue): VisualStateDefinition {
    return VISUAL_STATES[value];
}
