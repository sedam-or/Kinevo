import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import KButton from '../KButton.vue';
import KInput from '../KInput.vue';

describe('KButton (design.md §51, §95)', () => {
    it('renders a secondary button by default', () => {
        const wrapper = mount(KButton, { slots: { default: 'Save' } });
        expect(wrapper.element.tagName).toBe('BUTTON');
        expect(wrapper.attributes('type')).toBe('button');
        expect(wrapper.text()).toBe('Save');
        expect(wrapper.classes()).toContain('border');
    });

    it('emits click', async () => {
        const wrapper = mount(KButton, { slots: { default: 'Save' } });
        await wrapper.trigger('click');
        expect(wrapper.emitted('click')).toHaveLength(1);
    });

    it('exposes the primary variant class and is disabled when asked', () => {
        const wrapper = mount(KButton, { props: { variant: 'primary', disabled: true } });
        expect(wrapper.classes()).toContain('bg-primary');
        expect(wrapper.classes()).toContain('shadow-rest');
        expect((wrapper.element as HTMLButtonElement).disabled).toBe(true);
    });

    it('supports aria-label passthrough', () => {
        const wrapper = mount(KButton, { props: { 'aria-label': 'Close' } });
        expect(wrapper.attributes('aria-label')).toBe('Close');
    });
});

describe('KInput (design.md §95)', () => {
    it('two-way binds a value', async () => {
        const wrapper = mount(KInput, { props: { modelValue: 'hello' } });
        expect((wrapper.element as HTMLInputElement).value).toBe('hello');
        await wrapper.setValue('changed');
        expect(wrapper.emitted('update:modelValue')?.[0]?.[0]).toBe('changed');
    });

    it('passes through native attributes and is labelled', async () => {
        const wrapper = mount(KInput, {
            attrs: { id: 'email', 'aria-label': 'Email' },
        });
        expect(wrapper.attributes('id')).toBe('email');
        expect(wrapper.attributes('aria-label')).toBe('Email');
    });
});