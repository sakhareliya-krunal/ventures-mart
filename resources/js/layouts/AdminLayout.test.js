import { createPinia } from 'pinia';
import { mount, flushPromises } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
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
    vi.restoreAllMocks();
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

    const mainLinks = wrapper.findAll('aside nav > a');
    const lastMainLink = mainLinks[mainLinks.length - 1];
    expect(lastMainLink.attributes('href')).toBe('/admin/banners');
    expect(lastMainLink.text()).toContain('Banners');

    wrapper.unmount();
  });

  it('tracks the visual viewport height and removes its listener on unmount', async () => {
    let resizeHandler;
    let scheduledFrame;
    const visualViewport = {
      height: 719.2,
      addEventListener: vi.fn((event, handler) => {
        if (event === 'resize') resizeHandler = handler;
      }),
      removeEventListener: vi.fn(),
    };
    Object.defineProperty(window, 'visualViewport', { configurable: true, value: visualViewport });
    vi.spyOn(window, 'requestAnimationFrame').mockImplementation((callback) => {
      scheduledFrame = callback;
      return 1;
    });
    vi.spyOn(window, 'cancelAnimationFrame').mockImplementation(() => {});

    const router = createRouter({
      history: createMemoryHistory(),
      routes: [{ path: '/admin', component: { template: '<div />' } }],
    });
    await router.push('/admin');
    await router.isReady();

    const wrapper = mount(AdminLayout, {
      global: { plugins: [createPinia(), router], stubs: { Teleport: true } },
    });
    scheduledFrame();
    expect(wrapper.get('.admin-shell').attributes('style')).toContain('--admin-viewport-height: 720px');

    visualViewport.height = 680;
    resizeHandler();
    scheduledFrame();
    expect(wrapper.get('.admin-shell').attributes('style')).toContain('--admin-viewport-height: 680px');

    wrapper.unmount();
    expect(visualViewport.removeEventListener).toHaveBeenCalledWith('resize', resizeHandler);
  });

  afterEach(() => {
    vi.restoreAllMocks();
    Object.defineProperty(window, 'visualViewport', { configurable: true, value: undefined });
  });
});
