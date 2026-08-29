import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import OrderConfirmationPage from './OrderConfirmationPage.vue';

const { get } = vi.hoisted(() => ({
  get: vi.fn(),
}));

const { downloadOrderInvoice } = vi.hoisted(() => ({
  downloadOrderInvoice: vi.fn(),
}));

vi.mock('@/services/api', () => ({
  default: { get },
}));

vi.mock('@/utils/downloadInvoice', () => ({
  downloadOrderInvoice,
}));

vi.mock('@/stores/theme', () => ({
  useThemeStore: () => ({ brandName: 'Ventures Mart' }),
}));

vi.mock('@/stores/ui', () => ({
  useUiStore: () => ({ showToast: vi.fn() }),
}));

vi.mock('@/services/metaPixel', () => ({
  orderMetaParams: vi.fn(() => ({})),
  trackMetaEvent: vi.fn(),
}));

vi.mock('@unhead/vue', () => ({
  useHead: vi.fn(),
}));

vi.mock('vue-router', () => ({
  useRoute: () => ({ params: { id: 'VM-1001' } }),
  useRouter: () => ({ push: vi.fn() }),
}));

const order = {
  id: 11,
  number: 'VM 1001/A',
  payment_method: 'razorpay',
  payment_status: 'paid',
  invoice_available: true,
  total: 1499,
  items: [
    {
      product_id: 7,
      name: 'Steel Lunch Box',
      quantity: 1,
      unit_price: 1499,
      line_total: 1499,
      image: '',
    },
  ],
};

function mountPage() {
  return mount(OrderConfirmationPage, {
    global: {
      stubs: {
        AppButton: {
          props: ['to', 'variant', 'loading', 'type'],
          emits: ['click'],
          template: `
            <a v-if="to" class="button" :data-variant="variant" :href="to"><slot /></a>
            <button v-else class="button" :data-variant="variant" :type="type || 'button'" @click="$emit('click')"><slot /></button>
          `,
        },
        LoadingSpinner: { template: '<div>Loading</div>' },
        PageHero: { props: ['title', 'lead'], template: '<header><h1>{{ title }}</h1><p>{{ lead }}</p></header>' },
      },
    },
  });
}

describe('OrderConfirmationPage', () => {
  beforeEach(() => {
    get.mockReset();
    downloadOrderInvoice.mockReset();
    get.mockResolvedValue({ data: { data: order } });
    downloadOrderInvoice.mockResolvedValue(undefined);
  });

  it('shows premium order actions with track order and view all orders', async () => {
    const wrapper = mountPage();
    await flushPromises();

    const actions = wrapper.find('.order-confirm-actions');
    expect(actions.text()).toContain('Track order');
    expect(actions.text()).toContain('View all orders');
    expect(actions.text()).toContain('Continue shopping');
    expect(actions.text()).toContain('Download invoice');

    const trackLink = actions.findAll('a').find((link) => link.text().includes('Track order'));
    expect(trackLink.attributes('href')).toBe('/orders/VM%201001%2FA');
    expect(trackLink.attributes('data-variant')).toBe('primary');
  });

  it('keeps invoice download wired to the order id', async () => {
    const wrapper = mountPage();
    await flushPromises();

    const invoiceButton = wrapper.findAll('button').find((button) => button.text().includes('Download invoice'));
    await invoiceButton.trigger('click');

    expect(downloadOrderInvoice).toHaveBeenCalledWith(11);
  });
});
