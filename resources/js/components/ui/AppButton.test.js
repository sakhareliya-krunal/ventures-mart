import { mount, RouterLinkStub } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import AppButton from './AppButton.vue';

describe('AppButton', () => {
  it('renders slot content for a normal button', () => {
    const wrapper = mount(AppButton, {
      slots: { default: 'Save changes' },
      global: { stubs: { RouterLink: RouterLinkStub } },
    });

    expect(wrapper.find('button').text()).toContain('Save changes');
    expect(wrapper.find('.button-dots').exists()).toBe(false);
    expect(wrapper.find('button').attributes('aria-busy')).toBeUndefined();
  });

  it('hides content and disables the button while loading', () => {
    const wrapper = mount(AppButton, {
      props: { loading: true },
      slots: { default: 'Save changes' },
      global: { stubs: { RouterLink: RouterLinkStub } },
    });

    const button = wrapper.find('button');

    expect(button.attributes('disabled')).toBeDefined();
    expect(button.attributes('aria-busy')).toBe('true');
    expect(wrapper.find('.button--loading').exists()).toBe(true);
    expect(wrapper.find('.button__content').text()).toBe('Save changes');
    expect(wrapper.findAll('.button-dots__dot')).toHaveLength(5);
  });

  it('keeps disabled behavior separate from loading', () => {
    const wrapper = mount(AppButton, {
      props: { disabled: true },
      slots: { default: 'Save changes' },
      global: { stubs: { RouterLink: RouterLinkStub } },
    });

    expect(wrapper.find('button').attributes('disabled')).toBeDefined();
    expect(wrapper.find('.button-dots').exists()).toBe(false);
    expect(wrapper.find('button').attributes('aria-busy')).toBeUndefined();
  });
});