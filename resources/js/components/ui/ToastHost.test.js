import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';
import ToastHost from './ToastHost.vue';
import { useUiStore } from '@/stores/ui';

describe('ToastHost', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
    setActivePinia(createPinia());
  });

  it('renders standard success notifications with the success treatment', async () => {
    const ui = useUiStore();
    mount(ToastHost, { attachTo: document.body });

    ui.showToast('Address saved.', { type: 'success' });
    await Promise.resolve();

    const toast = document.body.querySelector('.app-toast');
    expect(toast).not.toBeNull();
    expect(toast?.classList.contains('app-toast--success')).toBe(true);
    expect(toast?.classList.contains('app-toast--error')).toBe(false);
    expect(document.body.textContent).toContain('Address saved.');
  });

  it('renders standard error notifications with the error treatment', async () => {
    const ui = useUiStore();
    mount(ToastHost, { attachTo: document.body });

    ui.showToast('Unable to save address.', { type: 'error' });
    await Promise.resolve();

    const toast = document.body.querySelector('.app-toast');
    expect(toast).not.toBeNull();
    expect(toast?.classList.contains('app-toast--error')).toBe(true);
    expect(toast?.classList.contains('app-toast--success')).toBe(false);
    expect(document.body.textContent).toContain('Unable to save address.');
  });

  it('renders rich order notifications without images', async () => {
    const ui = useUiStore();
    mount(ToastHost, { attachTo: document.body });

    ui.showOrderToast({
      title: 'Order placed',
      message: 'Pay by Cash on Delivery when your order arrives.',
      orderNumber: 'VM-COD56',
      paymentMethod: 'cod',
      paymentStatus: 'pending',
      total: 309,
      actionHref: '/orders/VM-COD56',
    });
    await Promise.resolve();

    expect(document.body.textContent).toContain('Order placed');
    expect(document.body.textContent).toContain('VM-COD56');
    expect(document.body.textContent).toContain('Cash on Delivery');
    const icon = document.body.querySelector('.app-toast__icon');
    expect(icon).not.toBeNull();
    expect(icon?.getAttribute('aria-hidden')).toBe('true');
    expect(icon?.getAttribute('style')).toContain('/favicon-48x48.png');
    expect(document.body.querySelector('img')).toBeNull();
    expect(document.body.querySelector('.app-toast__action')?.getAttribute('href')).toBe('/orders/VM-COD56');
  });
});
