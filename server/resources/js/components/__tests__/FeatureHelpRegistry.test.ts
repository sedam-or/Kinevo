import { beforeEach, describe, expect, it } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';

import FeatureHelp from '../FeatureHelp.vue';

describe('FeatureHelp registry integration (TASK-P20-011)', () => {
    beforeEach(() => {
        localStorage.clear();
    });

    it('falls back to the registry definition when title/body props are omitted', async () => {
        const wrapper = mount(FeatureHelp, { props: { id: 'hard-landscape' } });
        await flushPromises();
        await wrapper.find('button').trigger('click');
        await flushPromises();
        expect(wrapper.text()).toContain('Hard Landscape');
        expect(wrapper.text()).toContain('Fixed commitments');
    });
});
