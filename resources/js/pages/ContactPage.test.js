import { createPinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ContactPage from './ContactPage.vue';

const { post } = vi.hoisted(() => ({
  post: vi.fn(),
}));

vi.mock('@/services/api', () => ({
  default: { post },
}));

vi.mock('@unhead/vue', () => ({
  useHead: vi.fn(),
}));

describe('ContactPage success message', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    post.mockResolvedValue({
      data: { message: 'Thanks! Your message has been received.' },
    });
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('stays visible for five seconds after a successful submission', async () => {
    const wrapper = mount(ContactPage, {
      global: { plugins: [createPinia()] },
    });

    await wrapper.find('input[type="text"]').setValue('Test User');
    await wrapper.find('input[type="email"]').setValue('customer@example.com');
    await wrapper.find('textarea').setValue('Please confirm contact mail works.');

    await wrapper.find('form').trigger('submit');
    await flushPromises();

    expect(wrapper.find('.form-success').text()).toBe('Thanks! Your message has been received.');

    await vi.advanceTimersByTimeAsync(4999);
    expect(wrapper.find('.form-success').exists()).toBe(true);

    await vi.advanceTimersByTimeAsync(1);
    expect(wrapper.find('.form-success').exists()).toBe(false);

    wrapper.unmount();
  });
});
