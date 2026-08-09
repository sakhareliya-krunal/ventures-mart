import { createPinia } from 'pinia';
import { mount, flushPromises } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AdminLayout from './AdminLayout.vue';

const { get, patch } = vi.hoisted(() => ({
  get: vi.fn(),
  patch: vi.fn(),
}));

vi.mock('@/services/api', () => ({
  default: {
    get,
    patch,
    post: vi.fn(),
  },
}));

describe('AdminLayout navigation counts', () => {
  beforeEach(() => {
    window.matchMedia = vi.fn(() => ({
      matches: true,
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
    }));
    get.mockResolvedValue({
      data: {
        inventory_unread_count: 7,
        contact_unread_count: 120,
      },
    });
  });

  it('shows isolated capped badges for inventory and contact messages', async () => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [{ path: '/admin', component: { template: '<div />' }, meta: { title: 'Dashboard' } }],
    });
    await router.push('/admin');
    await router.isReady();

    const wrapper = mount(AdminLayout, {
      global: {
        plugins: [createPinia(), router],
        stubs: { Teleport: true },
      },
    });
    await flushPromises();

    const inventoryLink = wrapper.find('a[href="/admin/inventory"]');
    const contactsLink = wrapper.find('a[href="/admin/contacts"]');
    expect(inventoryLink.text()).toContain('7');
    expect(contactsLink.text()).toContain('99+');
    expect(contactsLink.find('.admin-nav-link__label').text()).toBe('Contact messages');
    expect(contactsLink.find('.admin-nav-link__count').text()).toBe('99+');
    expect(get).toHaveBeenCalledWith('/admin/navigation-counts', { skipErrorToast: true });

    wrapper.unmount();
  });
});
