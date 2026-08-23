/**
 * App-wide activity toasts (TASK-P17-011, design.md §104 micro-interactions:
 * "did my action work? what changed?"). Feedback, not decoration — one line,
 * auto-dismissed, announced politely to screen readers by ToastHost.
 */
import { defineStore } from 'pinia';

export interface ToastItem {
    id: number;
    message: string;
}

let nextId = 1;

export const useToastStore = defineStore('toast', {
    state: () => ({
        items: [] as ToastItem[],
        timers: new Map<number, ReturnType<typeof setTimeout>>(),
    }),
    actions: {
        push(message: string, timeoutMs = 3000): void {
            const id = nextId++;
            this.items.push({ id, message });
            const timer = setTimeout(() => this.dismiss(id), timeoutMs);
            this.timers.set(id, timer);
        },
        dismiss(id: number): void {
            const timer = this.timers.get(id);
            if (timer) {
                clearTimeout(timer);
                this.timers.delete(id);
            }
            this.items = this.items.filter((t) => t.id !== id);
        },
    },
});
