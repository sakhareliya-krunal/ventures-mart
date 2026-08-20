import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { createMemoryHistory, createRouter } from 'vue-router';
import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest';
import { nextTick } from 'vue';
import AppHeader from '@/components/common/AppHeader.vue';
import { useCartStore } from '@/stores/cart';

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/shop', component: { template: '<div />' } },
      { path: '/wishlist', component: { template: '<div />' } },
      { path: '/about', component: { template: '<div />' } },
      { path: '/blog', component: { template: '<div />' } },
      { path: '/contact', component: { template: '<div />' } },
      { path: '/login', component: { template: '<div />' } },
    ],
  });
}

async function mountHeader() {
  const pinia = createPinia();
  setActivePinia(pinia);
  const router = makeRouter();
  router.push('/');
  await router.isReady();

  const wrapper = mount(AppHeader, {
    attachTo: document.body,
    global: {
      plugins: [pinia, router],
    },
  });

  return { wrapper, router, cart: useCartStore() };
}

async function openMenu(wrapper) {
  await wrapper.get('[aria-label="Open menu"]').trigger('click');
  await nextTick();
}

async function toggleMenu(wrapper) {
  const label = document.querySelector('#mobile-navigation-drawer')
    ? 'Close menu'
    : 'Open menu';
  await wrapper.get(`[aria-label="${label}"]`).trigger('click');
  await nextTick();
}

describe('AppHeader mobile drawer', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
    document.body.style.overflow = '';
  });

  afterEach(() => {
    document.body.innerHTML = '';
    document.body.style.overflow = '';
    vi.restoreAllMocks();
  });

  test('opens the mobile drawer from the hamburger button', async () => {
    const { wrapper } = await mountHeader();

    await openMenu(wrapper);

    expect(document.querySelector('#mobile-navigation-drawer')).not.toBeNull();
    expect(document.body.style.overflow).toBe('hidden');
    expect(wrapper.get('[aria-label="Close menu"]').attributes('aria-expanded')).toBe('true');

    wrapper.unmount();
  });

  test('closes the mobile drawer when the header toggle is clicked again', async () => {
    const { wrapper } = await mountHeader();

    await openMenu(wrapper);
    await toggleMenu(wrapper);

    expect(document.querySelector('#mobile-navigation-drawer')).toBeNull();
    expect(document.body.style.overflow).toBe('');
    expect(wrapper.get('[aria-label="Open menu"]').attributes('aria-expanded')).toBe('false');

    wrapper.unmount();
  });

  test('closes the mobile drawer from backdrop and escape key', async () => {
    const { wrapper } = await mountHeader();

    await openMenu(wrapper);
    document.querySelector('.mobile-drawer__backdrop').click();
    await nextTick();

    expect(document.querySelector('#mobile-navigation-drawer')).toBeNull();
    expect(document.body.style.overflow).toBe('');

    await openMenu(wrapper);
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
    await nextTick();

    expect(document.querySelector('#mobile-navigation-drawer')).toBeNull();

    wrapper.unmount();
  });

  test('closes an open cart tray before opening the mobile drawer', async () => {
    const { wrapper, cart } = await mountHeader();
    cart.trayOpen = true;

    await openMenu(wrapper);

    expect(cart.trayOpen).toBe(false);
    expect(document.querySelector('#mobile-navigation-drawer')).not.toBeNull();

    wrapper.unmount();
  });

  test('closes the mobile drawer when a navigation link is selected', async () => {
    const { wrapper } = await mountHeader();

    await openMenu(wrapper);
    document.querySelector('.mobile-panel nav a[href="/about"]').click();
    await nextTick();

    expect(document.querySelector('#mobile-navigation-drawer')).toBeNull();

    wrapper.unmount();
  });
});
