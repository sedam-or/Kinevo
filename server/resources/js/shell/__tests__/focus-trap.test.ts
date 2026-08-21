import { afterEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, ref } from 'vue';
import { mount } from '@vue/test-utils';
import { useFocusTrap } from '../focus-trap';

function DialogHost(props: { onClose: () => void }) {
    return defineComponent({
        setup() {
            const root = ref<HTMLElement | null>(null);
            useFocusTrap(root, props.onClose);
            return () =>
                h('div', { ref: root, 'data-testid': 'dialog', tabindex: '-1' }, [
                    h('button', { 'data-testid': 'first' }, 'First'),
                    h('button', { 'data-testid': 'last' }, 'Last'),
                ]);
        },
    });
}

afterEach(() => {
    vi.restoreAllMocks();
    document.body.innerHTML = '';
});

describe('useFocusTrap (design.md §52, WCAG 2.2)', () => {
    it('moves initial focus to the first focusable element', async () => {
        const wrapper = mount(DialogHost({ onClose: vi.fn() }), { attachTo: document.body });
        await wrapper.vm.$nextTick();
        expect(document.activeElement?.getAttribute('data-testid')).toBe('first');
    });

    it('wraps Tab from the last element back to the first', async () => {
        const wrapper = mount(DialogHost({ onClose: vi.fn() }), { attachTo: document.body });
        await wrapper.vm.$nextTick();
        const last = wrapper.find('[data-testid="last"]').element as HTMLElement;
        last.focus();
        const tab = new KeyboardEvent('keydown', { key: 'Tab', bubbles: true, cancelable: true });
        document.dispatchEvent(tab);
        expect(document.activeElement?.getAttribute('data-testid')).toBe('first');
    });

    it('closes on Escape', async () => {
        const onClose = vi.fn();
        const wrapper = mount(DialogHost({ onClose }), { attachTo: document.body });
        await wrapper.vm.$nextTick();
        const esc = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true });
        document.dispatchEvent(esc);
        expect(onClose).toHaveBeenCalled();
    });

    it('restores focus to the previously focused element on unmount', async () => {
        const trigger = document.createElement('button');
        trigger.setAttribute('data-testid', 'trigger');
        document.body.appendChild(trigger);
        trigger.focus();

        const wrapper = mount(DialogHost({ onClose: vi.fn() }), { attachTo: document.body });
        await wrapper.vm.$nextTick();
        wrapper.unmount();
        await wrapper.vm.$nextTick();

        expect(document.activeElement?.getAttribute('data-testid')).toBe('trigger');
        document.body.removeChild(trigger);
    });
});
