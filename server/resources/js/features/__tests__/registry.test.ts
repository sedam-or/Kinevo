import { describe, expect, it } from 'vitest';
import { FEATURE_REGISTRY, featureDefinition } from '../registry';

/** TASK-P20-011 — single source of truth for feature explanations. */
describe('feature registry', () => {
    it('exposes definitions for every in-product FeatureHelp id', () => {
        for (const id of ['hard-landscape', 'capacity', 'adaptive-context', 'progress-events', 'dynamic-rescheduler', 'ai-proposal']) {
            expect(featureDefinition(id), id).toBeDefined();
            expect(featureDefinition(id)!.body.length).toBeGreaterThan(20);
        }
    });

    it('has no duplicate or empty entries', () => {
        const entries = Object.values(FEATURE_REGISTRY);
        expect(new Set(entries.map((e) => e.id)).size).toBe(entries.length);
        for (const e of entries) {
            expect(e.title.trim()).not.toBe('');
            expect(e.body.trim()).not.toBe('');
        }
    });
});
