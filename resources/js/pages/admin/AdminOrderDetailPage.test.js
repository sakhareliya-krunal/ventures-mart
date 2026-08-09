import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AdminOrderDetailPage from './AdminOrderDetailPage.vue';

const { get, post, patch, push } = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  patch: vi.fn(),
  push: vi.fn(),
}));

vi.mock('@/services/api', () => ({
  default: { get, post, patch },
}));

vi.mock('vue-router', () => ({
  useRoute: () => ({ params: { id: '7' } }),
  useRouter: () => ({ push }),
}));

vi.mock('@/utils/downloadInvoice', () => ({
  downloadOrderInvoice: vi.fn(),
}));

function baseOrder(overrides = {}) {
  return {
    id: 7,
    number: 'VM-FUL-7',
    status: 'Processing',
    payment_status: 'pending',
    payment_method: 'cod',
    fulfillment_method: 'shiprocket',
    can_switch_to_manual: true,
    courier_partner: null,
    awb_number: null,
    tracking_number: null,
    dispatched_at: '2026-08-09T09:15:00+05:30',
    expected_delivery_at: '2026-08-12T18:00:00+05:30',
    subtotal: 100,
    shipping: 0,
    cod_fee: 0,
    cgst: 2.5,
    sgst: 2.5,
    igst: 0,
    tax: 5,
    total: 105,
    seller_state: 'Gujarat',
    address: {
      full_name: 'Test Buyer',
      email: 'buyer@example.com',
      phone: '9999999999',
      address: '1 Test Street',
      city: 'Ahmedabad',
      state: 'Gujarat',
      postal_code: '380001',
    },
    user: null,
    items: [],
    shiprocket: {
      sync_status: 'processing',
      stage: 'order_created',
      shiprocket_order_id: 5001,
      shipment_id: 6001,
      courier_name: null,
      awb_code: null,
      pickup_status: null,
      shipment_status: null,
      last_synced_at: null,
      last_error: null,
      cancelled_at: null,
    },
    fulfillment_events: [{
      id: 1,
      event_type: 'method_assigned',
      reason: 'Assigned by server',
      created_at: '2026-08-09T08:00:00+05:30',
    }],
    ...overrides,
  };
}

function mountPage() {
  return mount(AdminOrderDetailPage, {
    global: {
      stubs: {
        AppButton: {
          props: ['disabled', 'variant'],
          emits: ['click'],
          template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
        },
        AppSelect: { template: '<div />' },
        FormField: {
          props: ['modelValue', 'label', 'disabled', 'type'],
          template: '<label>{{ label }}<input :value="modelValue" :disabled="disabled" /></label>',
        },
        LoadingSpinner: { template: '<div>Loading</div>' },
        InventoryReturnDialog: true,
        ConfirmDialog: {
          props: ['open', 'busy'],
          emits: ['confirm', 'update:open'],
          template: '<div v-if="open" class="confirm-stub"><button @click="$emit(\'confirm\')">Confirm switch</button></div>',
        },
      },
    },
  });
}

describe('AdminOrderDetailPage fulfillment controls', () => {
  beforeEach(() => {
    get.mockResolvedValue({ data: { data: baseOrder() } });
  });

  it('shows Shiprocket details read-only and switches only after confirmation', async () => {
    post.mockResolvedValue({
      data: {
        data: baseOrder({
          fulfillment_method: 'manual',
          can_switch_to_manual: false,
          shiprocket: {
            ...baseOrder().shiprocket,
            sync_status: 'cancelled',
            stage: 'switched_to_manual',
            cancelled_at: '2026-08-09T10:00:00+05:30',
          },
        }),
      },
    });
    const wrapper = mountPage();
    await flushPromises();

    expect(wrapper.text()).toContain('Shiprocket');
    expect(wrapper.text()).toContain('Dispatched');
    expect(wrapper.text()).toContain('Expected delivery');
    expect(wrapper.text()).not.toContain('Manual courier / tracking');

    const switchButton = wrapper.findAll('button').find((button) =>
      button.text().includes('Switch to manual'));
    await switchButton.trigger('click');
    expect(wrapper.find('.confirm-stub').exists()).toBe(true);

    await wrapper.find('.confirm-stub button').trigger('click');
    await flushPromises();

    expect(post).toHaveBeenCalledWith('/admin/orders/7/fulfillment/manual', {
      reason: 'Admin switched fulfillment to manual',
    });
    expect(wrapper.text()).toContain('Manual courier / tracking');
    expect(wrapper.text()).toContain('Previous Shiprocket shipment');
    expect(wrapper.text()).not.toContain('Retry fulfillment');
  });

  it('shows editable manual fields and hides Shiprocket actions for manual orders', async () => {
    get.mockResolvedValueOnce({
      data: {
        data: baseOrder({
          fulfillment_method: 'manual',
          can_switch_to_manual: false,
          shiprocket: null,
        }),
      },
    });
    const wrapper = mountPage();
    await flushPromises();

    expect(wrapper.text()).toContain('Manual courier / tracking');
    expect(wrapper.text()).toContain('Courier partner');
    expect(wrapper.text()).not.toContain('Retry fulfillment');
    expect(wrapper.text()).not.toContain('Switch to manual');

    const dispatchedField = wrapper.findAll('label').find((label) =>
      label.text().includes('Dispatched on'));
    const date = new Date('2026-08-09T09:15:00+05:30');
    const expectedDate = [
      date.getFullYear(),
      String(date.getMonth() + 1).padStart(2, '0'),
      String(date.getDate()).padStart(2, '0'),
    ].join('-');
    expect(dispatchedField.find('input').element.value).toBe(expectedDate);
  });

  it('keeps Shiprocket ownership visible when switching fails', async () => {
    post.mockRejectedValueOnce({
      response: { data: { message: 'Shiprocket could not confirm cancellation.' } },
    });
    const wrapper = mountPage();
    await flushPromises();

    const switchButton = wrapper.findAll('button').find((button) =>
      button.text().includes('Switch to manual'));
    await switchButton.trigger('click');
    await wrapper.find('.confirm-stub button').trigger('click');
    await flushPromises();

    expect(wrapper.text()).toContain('Shiprocket could not confirm cancellation.');
    expect(wrapper.text()).toContain('Shiprocket');
    expect(wrapper.text()).not.toContain('Manual courier / tracking');
  });

  it('shows mark refunded for prepaid cancelled orders', async () => {
    get.mockResolvedValueOnce({
      data: {
        data: baseOrder({
          status: 'Cancelled',
          payment_method: 'razorpay',
          payment_status: 'refund_pending',
          can_mark_refunded: true,
          cancelled_at: '2026-08-09T12:00:00+05:30',
          cancellation_reason: 'Customer request',
        }),
      },
    });
    patch.mockResolvedValueOnce({
      data: {
        data: baseOrder({
          status: 'Cancelled',
          payment_method: 'razorpay',
          payment_status: 'refunded',
          can_mark_refunded: false,
          cancelled_at: '2026-08-09T12:00:00+05:30',
          cancellation_reason: 'Customer request',
        }),
      },
    });

    const wrapper = mountPage();
    await flushPromises();

    expect(wrapper.text()).toContain('Customer request');
    const markRefunded = wrapper.findAll('button').find((button) =>
      button.text().includes('Mark refunded'));
    expect(markRefunded).toBeTruthy();
    await markRefunded.trigger('click');
    await flushPromises();

    expect(patch).toHaveBeenCalledWith('/admin/orders/7', {
      payment_status: 'refunded',
    });
  });
});
