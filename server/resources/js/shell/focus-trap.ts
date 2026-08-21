import { onBeforeUnmount, onMounted, type Ref } from 'vue';

function getFocusable(root: HTMLElement): HTMLElement[] {
    const selector = [
        'a[href]',
        'button:not([disabled])',
        'input:not([disabled])',
        'select:not([disabled])',
        'textarea:not([disabled])',
        '[tabindex]:not([tabindex="-1"])',
    ].join(',');
    return Array.from(root.querySelectorAll<HTMLElement>(selector));
}

/**
 * Basic modal focus trap (design.md §52, WCAG 2.2). Moves initial focus into
 * the dialog, traps Tab within it, closes on Escape, and restores focus to the
 * previously focused element on unmount. Safe for tests: no-op when the root
 * ref is missing.
 */
export function useFocusTrap(rootRef: Ref<HTMLElement | null>, onClose?: () => void): void {
    let previousFocus: HTMLElement | null = null;

    function trap(event: KeyboardEvent): void {
        const root = rootRef.value;
        if (!root) {
            return;
        }
        if (event.key === 'Escape') {
            event.preventDefault();
            onClose?.();
            return;
        }
        if (event.key !== 'Tab') {
            return;
        }
        const focusables = getFocusable(root);
        if (focusables.length === 0) {
            event.preventDefault();
            return;
        }
        const first = focusables[0];
        const last = focusables[focusables.length - 1];
        const active = document.activeElement as HTMLElement | null;
        if (event.shiftKey && (active === first || active === root || !root.contains(active))) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && (active === last || !root.contains(active))) {
            event.preventDefault();
            first.focus();
        }
    }

    function focusFirst(): void {
        const root = rootRef.value;
        if (!root) {
            return;
        }
        const focusables = getFocusable(root);
        (focusables[0] ?? root).focus();
    }
    onMounted(() => {
        previousFocus = document.activeElement as HTMLElement | null;
        focusFirst();
        document.addEventListener('keydown', trap);
    });

    onBeforeUnmount(() => {
        document.removeEventListener('keydown', trap);
        previousFocus?.focus?.();
    });
}
