import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import AppSelect from './AppSelect.vue';

vi.mock('@lucide/vue', () => {
  const Icon = { template: '<span aria-hidden="true" />' };
  return { Check: Icon, ChevronDown: Icon };
});

let wrapper;

const options = [
  { value: 'pending', label: 'Pending' },
  { value: 'paid', label: 'Paid' },
  { value: 'failed', label: 'Failed' },
];

function mountSelect(props = {}) {
  wrapper = mount(AppSelect, {
    attachTo: document.body,
    props: {
      modelValue: '',
      options,
      ariaLabel: 'Payment status',
      ...props,
    },
  });

  return wrapper;
}

describe('AppSelect', () => {
  afterEach(() => {
    wrapper?.unmount();
    wrapper = undefined;
  });

  it('opens from the trigger and closes after selecting an option', async () => {
    wrapper = mountSelect();
    const trigger = wrapper.find('.app-select__trigger');

    await trigger.trigger('click');
    expect(trigger.attributes('aria-expanded')).toBe('true');

    await wrapper.findAll('[role="option"]')[1].trigger('click');

    expect(wrapper.emitted('update:modelValue')).toEqual([['paid']]);
    expect(trigger.attributes('aria-expanded')).toBe('false');
  });

  it('closes after keyboard selection', async () => {
    wrapper = mountSelect({ modelValue: 'pending' });
    const trigger = wrapper.find('.app-select__trigger');

    await trigger.trigger('keydown', { key: 'ArrowDown' });
    await wrapper.find('[role="listbox"]').trigger('keydown', { key: 'Enter' });

    expect(wrapper.emitted('update:modelValue')).toEqual([['pending']]);
    expect(trigger.attributes('aria-expanded')).toBe('false');
  });

  it('closes on outside click', async () => {
    wrapper = mountSelect();
    const trigger = wrapper.find('.app-select__trigger');

    await trigger.trigger('click');
    document.body.click();
    await wrapper.vm.$nextTick();

    expect(trigger.attributes('aria-expanded')).toBe('false');
  });

  it('does not open when disabled', async () => {
    wrapper = mountSelect({ disabled: true });
    const trigger = wrapper.find('.app-select__trigger');

    await trigger.trigger('click');

    expect(trigger.attributes('aria-expanded')).toBe('false');
  });

  it('renders form label and error text', () => {
    wrapper = mountSelect({ label: 'Status', error: 'Choose a status', required: true });

    expect(wrapper.find('.app-select__label').text()).toContain('Status');
    expect(wrapper.find('.app-select__error').text()).toBe('Choose a status');
    expect(wrapper.find('.app-select__trigger').attributes('aria-invalid')).toBe('true');
  });
});
