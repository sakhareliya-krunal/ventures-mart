import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import CheckoutPage from './CheckoutPage.vue';

const { get, post, patch, push, showToast, showOrderToast, trackMetaEvent } = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  patch: vi.fn(),
  push: vi.fn(),
  showToast: vi.fn(),
  showOrderToast: vi.fn(),
  trackMetaEvent: vi.fn(),
}));

vi.mock('@/services/api', () => ({
  default: { get, post, patch },
}));

vi.mock('@/services/metaPixel', () => ({
  cartMetaParams: (items, total) => ({
    content_ids: items.map((item) => String(item.product_id || item.id)).filter(Boolean),
    currency: 'INR',
    value: Number(total || 0),
  }),
  trackMetaEvent,
}));

vi.mock('vue-router', () => ({
  useRouter: () => ({ push }),
}));

vi.mock('@unhead/vue', () => ({
  useHead: vi.fn(),
}));

vi.mock('@/stores/theme', () => ({
  useThemeStore: () => ({ brandName: 'Ventures Mart' }),
}));

vi.mock('@/stores/ui', () => ({
  useUiStore: () => ({ showToast, showOrderToast }),
}));

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    user: { id: 1, name: 'Buyer', email: 'buyer@example.com' },
  }),
}));

vi.mock('@/stores/cart', () => ({
  useCartStore: () => ({
    items: [{ id: 1, product_id: 10, quantity: 1 }],
    totals: {
      subtotal: 200,
      shipping: 0,
      tax: 10,
      cgst: 5,
      sgst: 5,
      igst: 0,
      total: 210,
    },
    fetch: vi.fn(),
  }),
}));

vi.mock('@/utils/seoHead', () => ({
  seoHeadFromServer: () => ({}),
}));

function mountPage() {
  return mount(CheckoutPage, {
    global: {
      stubs: {
        PageHero: true,
        OrderSummary: true,
        AppButton: {
          props: ['disabled', 'type', 'variant'],
          emits: ['click'],
          template: '<button :type="type || \'button\'" :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
        },
        FormField: {
          props: ['modelValue', 'label'],
          emits: ['update:modelValue'],
          template: '<label>{{ label }}<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /></label>',
        },
        SearchableSelect: {
          props: ['modelValue', 'label'],
          emits: ['update:modelValue'],
          template: '<label>{{ label }}<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /></label>',
        },
      },
    },
  });
}

describe('CheckoutPage address and payment cards', () => {
  beforeEach(() => {
    get.mockReset();
    post.mockReset();
    patch.mockReset();
    showToast.mockReset();
    showOrderToast.mockReset();
    trackMetaEvent.mockReset();
    push.mockReset();
    delete window.Razorpay;

    get.mockImplementation((url) => {
      if (url === '/addresses') {
        return Promise.resolve({
          data: {
            data: [{
              id: 7,
              label: 'Home',
              full_name: 'Buyer',
              phone: '9999999999',
              address: '12 Street',
              city: 'Rajkot',
              district: 'Rajkot',
              state: 'Gujarat',
              postal_code: '360001',
              is_default: true,
            }],
          },
        });
      }
      return Promise.resolve({ data: {} });
    });
  });

  it('shows saved addresses without the full shipping form until Add address', async () => {
    const wrapper = mountPage();
    await flushPromises();

    expect(wrapper.text()).toContain('Home');
    expect(wrapper.text()).toContain('12 Street');
    expect(wrapper.find('.checkout-addresses__add').exists()).toBe(false);
    expect(wrapper.findAll('label').some((label) => label.text().includes('Email'))).toBe(false);

    await wrapper.find('.checkout-addresses__add-btn').trigger('click');
    expect(wrapper.find('.checkout-addresses__add').exists()).toBe(true);
    expect(wrapper.text()).toContain('Add address');
  });

  it('does not flash the full shipping form while addresses are loading', async () => {
    let resolveAddresses;
    get.mockImplementation((url) => {
      if (url === '/addresses') {
        return new Promise((resolve) => {
          resolveAddresses = resolve;
        });
      }
      return Promise.resolve({ data: {} });
    });

    const wrapper = mountPage();
    await flushPromises();

    expect(wrapper.find('.checkout-addresses__loading [aria-label]').attributes('aria-label')).toContain('Loading addresses');
    expect(wrapper.findAll('label').some((label) => label.text().includes('Email'))).toBe(false);
    expect(wrapper.find('.checkout-addresses__list').exists()).toBe(false);

    resolveAddresses({
      data: {
        data: [{
          id: 7,
          label: 'Home',
          full_name: 'Buyer',
          phone: '9999999999',
          address: '12 Street',
          city: 'Rajkot',
          district: 'Rajkot',
          state: 'Gujarat',
          postal_code: '360001',
          is_default: true,
        }],
      },
    });
    await flushPromises();

    expect(wrapper.find('.checkout-addresses__loading [aria-label]').exists()).toBe(false);
    expect(wrapper.text()).toContain('Home');
    expect(wrapper.findAll('label').some((label) => label.text().includes('Email'))).toBe(false);
  });

  it('compares online and COD totals with a 99 charge', async () => {
    const wrapper = mountPage();
    await flushPromises();

    expect(wrapper.text()).toContain('Pay online');
    expect(wrapper.text()).toContain('Cash on Delivery');
    expect(wrapper.text()).toContain('₹210');
    expect(wrapper.text()).toContain('₹309');
    expect(wrapper.text()).toContain('+₹99 COD charge');
  });

  it('tracks AddPaymentInfo when the Razorpay payment screen opens', async () => {
    post.mockImplementation((url) => {
      if (url === '/orders') {
        return Promise.resolve({
          data: {
            data: { id: 55, number: 'VM-TEST55', total: 210 },
            razorpay: {
              key: 'rzp_test_dummy',
              order_id: 'order_meta_55',
              amount: 21000,
              currency: 'INR',
              name: 'Ventures Mart',
            },
          },
        });
      }

      if (url === '/orders/55/payment/verify') {
        return Promise.resolve({ data: { data: { id: 55 } } });
      }

      return Promise.resolve({ data: {} });
    });

    window.Razorpay = vi.fn(function Razorpay(options) {
      this.on = vi.fn();
      this.open = vi.fn(() => options.handler({
        razorpay_order_id: 'order_meta_55',
        razorpay_payment_id: 'pay_meta_55',
        razorpay_signature: 'sig_meta_55',
      }));
    });

    const wrapper = mountPage();
    await flushPromises();

    await wrapper.find('form').trigger('submit');
    await flushPromises();

    expect(trackMetaEvent).toHaveBeenCalledWith('AddPaymentInfo', expect.objectContaining({
      content_ids: ['10'],
      currency: 'INR',
      value: 210,
      payment_type: 'razorpay',
      order_id: 'VM-TEST55',
    }));
    expect(window.Razorpay).toHaveBeenCalled();
    expect(post).toHaveBeenCalledWith('/orders/55/payment/verify', {
      razorpay_order_id: 'order_meta_55',
      razorpay_payment_id: 'pay_meta_55',
      razorpay_signature: 'sig_meta_55',
    });
    expect(showOrderToast).toHaveBeenCalledWith(expect.objectContaining({
      title: 'Payment successful',
      orderNumber: 'VM-TEST55',
      paymentMethod: 'razorpay',
      total: 210,
      actionHref: '/orders/VM-TEST55',
    }));
  });

  it('shows a rich text-only notification after COD checkout', async () => {
    post.mockImplementation((url) => {
      if (url === '/orders') {
        return Promise.resolve({
          data: {
            data: {
              id: 56,
              number: 'VM-COD56',
              payment_method: 'cod',
              payment_status: 'pending',
              total: 309,
            },
          },
        });
      }

      return Promise.resolve({ data: {} });
    });

    const wrapper = mountPage();
    await flushPromises();

    await wrapper.find('input[value="cod"]').setValue();
    await wrapper.find('form').trigger('submit');
    await flushPromises();

    expect(showOrderToast).toHaveBeenCalledWith(expect.objectContaining({
      title: 'Order placed',
      orderNumber: 'VM-COD56',
      paymentMethod: 'cod',
      paymentStatus: 'pending',
      total: 309,
      actionHref: '/orders/VM-COD56',
    }));
    expect(showToast).not.toHaveBeenCalledWith('Order placed. Pay cash on delivery.', expect.anything());
  });
});
