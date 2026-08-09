import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import CheckoutPage from './CheckoutPage.vue';

const { get, post, patch, push, showToast } = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  patch: vi.fn(),
  push: vi.fn(),
  showToast: vi.fn(),
}));

vi.mock('@/services/api', () => ({
  default: { get, post, patch },
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
  useUiStore: () => ({ showToast }),
}));

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    user: { id: 1, name: 'Buyer', email: 'buyer@example.com' },
  }),
}));

vi.mock('@/stores/cart', () => ({
  useCartStore: () => ({
    items: [{ id: 1, quantity: 1 }],
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

    expect(wrapper.text()).toContain('Loading addresses…');
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

    expect(wrapper.text()).not.toContain('Loading addresses…');
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
});
