import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';

import InlineError from '../InlineError.vue';

describe('InlineError (P28-011 / RET-013)', () => {
    it('renders the message and a retry control', () => {
        const wrapper = mount(InlineError, { props: { message: 'Could not load goals.' } });
        expect(wrapper.get('[data-testid="inline-error"]').text()).toContain('Could not load goals.');
        expect(wrapper.find('[data-testid="inline-error-retry"]').exists()).toBe(true);
        expect(wrapper.attributes('role')).toBe('alert');
    });

    it('emits retry when the recovery action is triggered', async () => {
        const wrapper = mount(InlineError, { props: { message: 'Offline.' } });
        await wrapper.get('[data-testid="inline-error-retry"]').trigger('click');
        expect(wrapper.emitted('retry')).toHaveLength(1);
    });
});
