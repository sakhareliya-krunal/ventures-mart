import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import OrderTrackPage from './OrderTrackPage.vue';

const { get, post, push, downloadTrackedOrderInvoice } = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  push: vi.fn(),
  downloadTrackedOrderInvoice: vi.fn(),
}));

vi.mock('@/services/api', () => ({
  default: { get, post },
}));

vi.mock('vue-router', () => ({
  useRoute: () => ({ params: { number: 'VM-TRACK-1' } }),
  useRouter: () => ({ push }),
  RouterLink: {
    props: ['to'],
    template: '<a :href="typeof to === \'string\' ? to : \'#\'"><slot /></a>',
  },
}));

vi.mock('@unhead/vue', () => ({
  useHead: vi.fn(),
}));

vi.mock('@/stores/theme', () => ({
  useThemeStore: () => ({ brandName: 'Ventures Mart' }),
}));

vi.mock('@/stores/ui', () => ({
  useUiStore: () => ({ showToast: vi.fn() }),
}));

vi.mock('@/utils/downloadInvoice', () => ({
  downloadTrackedOrderInvoice,
}));

function trackPayload(overrides = {}) {
  return {
    id: 11,
    number: 'VM-TRACK-1',
    status: 'Processing',
    status_label: 'Confirmed',
    payment_method: 'cod',
    payment_status: 'pending',
    expected_delivery_at: '2026-08-16T12:00:00+05:30',
    invoice_available: true,
    can_cancel: true,
    can_request_replacement: false,
    replacement_requests: [],
    timeline: {
      confirmed: true,
      packed: false,
      shipped: false,
      delivered: false,
    },
    courier: {
      has_details: true,
      partner: 'Shiprocket',
      awb_number: 'AWB123',
      expected_delivery_at: '2026-08-16T12:00:00+05:30',
    },
    shipment: {
      tracking_url: 'https://track.example.test/AWB123',
      shipment_status: 'In transit',
      pickup_status: 'Picked up',
      last_synced_at: '2026-08-10T12:00:00+05:30',
    },
    location: {
      city: 'Rajkot',
      district: 'Rajkot',
      state: 'Gujarat',
    },
    customer: {
      full_name: 'Track Buyer',
      email: 'track@example.com',
      phone: '9999999999',
    },
    address: {
      address: '1 Street',
      city: 'Rajkot',
      district: 'Rajkot',
      state: 'Gujarat',
      postal_code: '360001',
    },
    items: [{
      name: 'Toy',
      quantity: 1,
      unit_price: 100,
      line_total: 100,
      image: '/images/products/demo.jpg',
    }],
    totals: {
      subtotal: 100,
      shipping: 0,
      cod_fee: 0,
      cgst: 2.5,
      sgst: 2.5,
      igst: 0,
      tax: 5,
      total: 105,
    },
    support: {
      email: 'help@example.com',
      phone: '1800',
      replacement_path: '/replacement',
    },
    ...overrides,
  };
}

function mountPage() {
  return mount(OrderTrackPage, {
    global: {
      stubs: {
        AppButton: {
          props: ['disabled', 'type', 'variant', 'to', 'loading'],
          emits: ['click'],
          template: `
            <a v-if="to" class="button" :data-variant="variant" :href="to"><slot /></a>
            <button v-else class="button" :disabled="disabled" :data-variant="variant" :type="type || 'button'" @click="$emit('click')"><slot /></button>
          `,
        },
        LoadingSpinner: { template: '<div>Loading</div>' },
      },
    },
  });
}

describe('OrderTrackPage cancel and replacement', () => {
  beforeEach(() => {
    get.mockReset();
    post.mockReset();
    push.mockReset();
    downloadTrackedOrderInvoice.mockReset();
  });

  it('shows the premium tracking summary and shipment link', async () => {
    get.mockResolvedValueOnce({ data: { data: trackPayload() } });

    const wrapper = mountPage();
    await flushPromises();

    const summary = wrapper.find('.order-track-summary');
    expect(summary.exists()).toBe(true);
    expect(summary.text()).toContain('VM-TRACK-1');
    expect(summary.text()).toContain('Confirmed');
    expect(summary.text()).toContain('Estimated delivery');
    expect(summary.text()).toContain('Rajkot, Rajkot, Gujarat');

    const shipmentLink = summary.find('a.order-track__summary-link');
    expect(shipmentLink.attributes('href')).toBe('https://track.example.test/AWB123');
    expect(shipmentLink.text()).toContain('Track shipment');
    expect(wrapper.find('.order-track-timeline__step.is-current').text()).toContain('Packed');
  });

  it('downloads the tracked order invoice from the actions card', async () => {
    get.mockResolvedValueOnce({ data: { data: trackPayload() } });
    downloadTrackedOrderInvoice.mockResolvedValue(undefined);

    const wrapper = mountPage();
    await flushPromises();

    const invoiceButton = wrapper.findAll('button').find((button) => button.text().includes('Download invoice'));
    await invoiceButton.trigger('click');

    expect(downloadTrackedOrderInvoice).toHaveBeenCalledWith('VM-TRACK-1');
  });

  it('cancels an eligible order from the track page', async () => {
    get
      .mockResolvedValueOnce({ data: { data: trackPayload() } })
      .mockResolvedValueOnce({
        data: {
          data: trackPayload({
            status: 'Cancelled',
            status_label: 'Cancelled',
            can_cancel: false,
            cancelled_at: '2026-08-09T12:00:00+05:30',
            cancellation_reason: 'Changed my mind',
          }),
        },
      });
    post.mockResolvedValue({
      data: {
        message: 'Order cancelled.',
        data: {},
      },
    });

    const wrapper = mountPage();
    await flushPromises();

    const cancelToggle = wrapper.findAll('button').find((button) => button.text().includes('Cancel order'));
    expect(cancelToggle).toBeTruthy();
    await cancelToggle.trigger('click');

    await wrapper.find('textarea').setValue('Changed my mind');
    await wrapper.find('form.order-track__form').trigger('submit.prevent');
    await flushPromises();

    expect(post).toHaveBeenCalledWith('/orders/11/cancel', {
      cancellation_reason: 'Changed my mind',
    });
    expect(wrapper.text()).toContain('Cancelled');
  });

  it('submits a replacement request for delivered orders', async () => {
    get
      .mockResolvedValueOnce({
        data: {
          data: trackPayload({
            status: 'Delivered',
            status_label: 'Delivered',
            can_cancel: false,
            can_request_replacement: true,
            timeline: {
              confirmed: true,
              packed: true,
              shipped: true,
              delivered: true,
            },
          }),
        },
      })
      .mockResolvedValueOnce({
        data: {
          data: trackPayload({
            status: 'Delivered',
            status_label: 'Delivered',
            can_cancel: false,
            can_request_replacement: false,
            replacement_requests: [{
              id: 9,
              status: 'requested',
              reason: 'defective',
              replacement_order: null,
            }],
            timeline: {
              confirmed: true,
              packed: true,
              shipped: true,
              delivered: true,
            },
          }),
        },
      });
    post.mockResolvedValue({
      data: {
        message: 'Replacement request submitted.',
        data: {
          id: 9,
          status: 'requested',
          reason: 'defective',
          replacement_order: null,
        },
      },
    });

    const wrapper = mountPage();
    await flushPromises();

    const requestToggle = wrapper.findAll('button').find((button) => button.text().includes('Request replacement'));
    await requestToggle.trigger('click');

    await wrapper.find('select').setValue('defective');
    await wrapper.find('textarea').setValue('Not working');
    await wrapper.find('form.order-track__form').trigger('submit.prevent');
    await flushPromises();

    expect(post).toHaveBeenCalled();
    const [url, body] = post.mock.calls[0];
    expect(url).toBe('/orders/11/replacement-requests');
    expect(body).toBeInstanceOf(FormData);
    expect(body.get('reason')).toBe('defective');
    expect(body.get('notes')).toBe('Not working');
    expect(wrapper.text()).toContain('defective');
  });
});
