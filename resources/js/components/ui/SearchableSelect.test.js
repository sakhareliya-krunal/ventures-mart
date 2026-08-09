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

    await wrapper.find('.searchable-select__trigger').trigger('click');
    await wrapper.find('input[type="search"]').setValue('guj');

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

    await wrapper.find('.searchable-select__trigger').trigger('click');
    const search = wrapper.find('input[type="search"]');
    await search.trigger('keydown', { key: 'ArrowDown' });
    await search.trigger('keydown', { key: 'Enter' });
    expect(wrapper.emitted('update:modelValue')).toEqual([['Gujarat']]);

    await wrapper.find('.searchable-select__trigger').trigger('click');
    await wrapper.find('input[type="search"]').setValue('not available');
    expect(wrapper.text()).toContain('No matching options');
  });
});
