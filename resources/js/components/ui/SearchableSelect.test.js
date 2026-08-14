import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import SearchableSelect from './SearchableSelect.vue';

vi.mock('@lucide/vue', () => {
  const Icon = { template: '<span aria-hidden="true" />' };
  return { Check: Icon, ChevronDown: Icon, Search: Icon };
});

const options = [
  { value: 'Goa', label: 'Goa' },
  { value: 'Gujarat', label: 'Gujarat' },
  { value: 'Maharashtra', label: 'Maharashtra' },
];

describe('SearchableSelect', () => {
  it('filters options and emits the selected value', async () => {
    const wrapper = mount(SearchableSelect, {
      props: {
        label: 'State',
        modelValue: '',
        options,
        searchPlaceholder: 'Search states…',
      },
    });

    const input = wrapper.find('input[role="combobox"]');
    await input.trigger('focus');
    await input.setValue('guj');

    expect(wrapper.findAll('[role="option"]')).toHaveLength(1);
    expect(wrapper.find('[role="option"]').text()).toContain('Gujarat');

    await wrapper.find('[role="option"]').trigger('click');
    expect(wrapper.emitted('update:modelValue')).toEqual([['Gujarat']]);
  });

  it('supports keyboard selection and an empty state', async () => {
    const wrapper = mount(SearchableSelect, {
      props: {
        label: 'District',
        modelValue: '',
        options,
      },
    });

    const input = wrapper.find('input[role="combobox"]');
    await input.trigger('focus');
    await input.trigger('keydown', { key: 'ArrowDown' });
    await input.trigger('keydown', { key: 'Enter' });
    expect(wrapper.emitted('update:modelValue')).toEqual([['Gujarat']]);

    await input.trigger('focus');
    await input.setValue('not available');
    expect(wrapper.text()).toContain('No matching options');
  });
});
