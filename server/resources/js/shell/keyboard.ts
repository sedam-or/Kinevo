import { onBeforeUnmount, onMounted } from 'vue';
import type { ShellView } from './navigation';

/**
 * Global keyboard shortcuts (design.md §46). Two-key chords (`G` prefix)
 * navigate the primary shell; `Cmd/Ctrl+K` opens Quick Capture. Shortcuts are
 * ignored while the user is typing in a text input to avoid conflicts (§46).
 */
const NAV_CHORDS: Record<string, ShellView> = {
    t: 'today',
    w: 'week',
    c: 'calendar',
    g: 'goals',
    k: 'knowledge',
};

function isEditableTarget(event: KeyboardEvent): boolean {
    const el = event.target as HTMLElement | null;
    if (!el) {
        return false;
    }
    const tag = el.tagName;
    return (
        tag === 'INPUT' ||
        tag === 'TEXTAREA' ||
        tag === 'SELECT' ||
        el.isContentEditable ||
        (event.composedPath?.() ?? []).some((n) => n instanceof HTMLElement && n.isContentEditable)
    );
}

export interface KeyboardShortcutOptions {
    onNavigate(view: ShellView): void;
    onQuickCapture(): void;
    /** TASK-P20-033 — Cmd/Ctrl+Shift+K opens the unified command palette. */
    onCommandPalette(): void;
}

export function useKeyboardShortcuts(options: KeyboardShortcutOptions): void {
    let gPending = false;
    let gTimer: ReturnType<typeof setTimeout> | undefined;

    function clearPending(): void {
        gPending = false;
        if (gTimer !== undefined) {
            clearTimeout(gTimer);
            gTimer = undefined;
        }
    }

    function onKeyDown(event: KeyboardEvent): void {
        // Cmd/Ctrl + K → Quick Capture; Cmd/Ctrl + Shift + K → Command
        // Palette (TASK-P20-033). Neither fires while typing in fields.
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            if (event.shiftKey) {
                options.onCommandPalette();
            } else {
                options.onQuickCapture();
            }
            clearPending();

            return;
        }

        if (isEditableTarget(event)) {
            return;
        }

        const key = event.key.toLowerCase();

        // "G" begins a navigation chord: G then T/W/C/G/K.
        if (gPending) {
            if (gTimer !== undefined) {
                clearTimeout(gTimer);
            }
            gTimer = undefined;
            const view = NAV_CHORDS[key];
            if (view) {
                event.preventDefault();
                options.onNavigate(view);
            }
            gPending = false;
            return;
        }

        if (key === 'g') {
            gPending = true;
            gTimer = setTimeout(clearPending, 800);
        }
    }

    onMounted(() => {
        window.addEventListener('keydown', onKeyDown);
    });

    onBeforeUnmount(() => {
        window.removeEventListener('keydown', onKeyDown);
        clearPending();
    });
}
