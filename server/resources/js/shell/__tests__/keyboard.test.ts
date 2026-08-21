import { afterEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import { mount } from '@vue/test-utils';
import { useKeyboardShortcuts } from '../keyboard';

function Host(props: { onNavigate: (v: string) => void; onQuickCapture: () => void }) {
    return defineComponent({
        setup() {
            useKeyboardShortcuts({
                onNavigate: props.onNavigate,
                onQuickCapture: props.onQuickCapture,
            });
            return () => h('div', { 'data-testid': 'host' });
        },
    });
}

function fireKey(key: string, opts: { ctrl?: boolean; meta?: boolean; target?: HTMLElement } = {}) {
    const event = new KeyboardEvent('keydown', {
        key,
        ctrlKey: opts.ctrl ?? false,
        metaKey: opts.meta ?? false,
        bubbles: true,
        cancelable: true,
    });
    if (opts.target) {
        Object.defineProperty(event, 'target', { value: opts.target, configurable: true });
    }
    window.dispatchEvent(event);
}

afterEach(() => {
    vi.restoreAllMocks();
});

describe('useKeyboardShortcuts (design.md §46)', () => {
    it('navigates with G then T / W / C / G / K chords', () => {
        const onNavigate = vi.fn();
        mount(Host({ onNavigate, onQuickCapture: vi.fn() }));

        fireKey('g');
        fireKey('t');
        expect(onNavigate).toHaveBeenCalledWith('today');

        fireKey('g');
        fireKey('w');
        expect(onNavigate).toHaveBeenCalledWith('week');

        fireKey('g');
        fireKey('c');
        expect(onNavigate).toHaveBeenCalledWith('calendar');

        fireKey('g');
        fireKey('g');
        expect(onNavigate).toHaveBeenCalledWith('goals');

        fireKey('g');
        fireKey('k');
        expect(onNavigate).toHaveBeenCalledWith('knowledge');
    });

    it('opens Quick Capture with Ctrl/Cmd + K', () => {
        const onQuickCapture = vi.fn();
        mount(Host({ onNavigate: vi.fn(), onQuickCapture }));

        fireKey('k', { ctrl: true });
        expect(onQuickCapture).toHaveBeenCalled();

        onQuickCapture.mockClear();
        fireKey('k', { meta: true });
        expect(onQuickCapture).toHaveBeenCalled();
    });

    it('does not navigate while typing in an input', () => {
        const onNavigate = vi.fn();
        mount(Host({ onNavigate, onQuickCapture: vi.fn() }));
        const input = document.createElement('input');

        fireKey('g', { target: input });
        fireKey('t', { target: input });
        expect(onNavigate).not.toHaveBeenCalled();
    });

    it('ignores an incomplete chord (G followed by an unknown key)', () => {
        const onNavigate = vi.fn();
        mount(Host({ onNavigate, onQuickCapture: vi.fn() }));

        fireKey('g');
        fireKey('z');
        expect(onNavigate).not.toHaveBeenCalled();
    });
});
