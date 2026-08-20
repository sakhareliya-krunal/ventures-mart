import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { createMemoryHistory, createRouter } from 'vue-router';
import { afterEach, beforeEach, describe, expect, test } from 'vitest';
import { nextTick } from 'vue';
import CartTray from '@/components/cart/CartTray.vue';
import { useCartStore } from '@/stores/cart';
import { isScrollLocked, resetScrollLock } from '@/utils/scrollLock';

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/', name: 'home', component: { template: '<div />' } }],
  });
}

describe('CartTray scroll lock', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
    resetScrollLock();
  });

  afterEach(() => {
    resetScrollLock();
    document.body.innerHTML = '';
  });

  test('locks scroll when the cart tray is visible and unlocks when closed', async () => {
    const pinia = createPinia();
    setActivePinia(pinia);
    const router = makeRouter();
    await router.push('/');
    await router.isReady();

    const wrapper = mount(CartTray, {
      attachTo: document.body,
      global: {
        plugins: [pinia, router],
      },
    });

    const cart = useCartStore();
    cart.items = [
      {
        product_id: 1,
        quantity: 1,
        product: {
          id: 1,
          name: 'Test Toy',
          price: 499,
          slug: 'test-toy',
          image_url: '/images/test.jpg',
          stock: 5,
        },
      },
    ];
    cart.itemCount = 1;
    cart.quantityCount = 1;
    cart.totals = {
      subtotal: 499,
      shipping: 0,
      cgst: 0,
      sgst: 0,
      igst: 0,
      tax: 0,
      tax_type: 'estimate',
      total: 499,
    };
    cart.trayOpen = true;
    await nextTick();

    expect(document.querySelector('.cart-tray')).not.toBeNull();
    expect(isScrollLocked()).toBe(true);

    cart.closeTray();
    await nextTick();

    expect(document.querySelector('.cart-tray')).toBeNull();
    expect(isScrollLocked()).toBe(false);

    wrapper.unmount();
  });
});
