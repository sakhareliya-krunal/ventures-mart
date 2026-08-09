import { flushPromises, mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AdminInventoryPage from './AdminInventoryPage.vue';

const { get } = vi.hoisted(() => ({ get: vi.fn() }));

vi.mock('@/services/api', () => ({
  default: {
    get,
    post: vi.fn(),
  },
}));

vi.mock('vue-router', () => ({
  useRoute: () => ({ query: {} }),
}));

vi.mock('@lucide/vue', () => {
  const Icon = { template: '<span aria-hidden="true" />' };
  return {
    AlertTriangle: Icon,
    Boxes: Icon,
    Check: Icon,
    CheckSquare: Icon,
    ChevronDown: Icon,
    Download: Icon,
    PackageCheck: Icon,
    PackageMinus: Icon,
    RefreshCw: Icon,
  };
});

function inventoryResponse() {
  return {
    data: {
      data: [{
        product_id: 41,
        product: {
          id: 41,
          name: 'Test Robot',
          sku: 'ROBOT-41',
          image: '/images/robot.jpg',
        },
        on_hand: 8,
        reserved: 2,
        committed: 1,
        available: 5,
        version: 3,
        low_stock_threshold: 5,
        reorder_point: 10,
        is_low_stock: true,
      }],
      meta: {
        current_page: 1,
        last_page: 1,
        from: 1,
        to: 1,
        total: 1,
      },
    },
  };
}

function mountPage() {
  return mount(AdminInventoryPage, {
    global: {
      stubs: {
        AdminSearchField: { template: '<input aria-label="Search inventory" />' },
        AppButton: { template: '<button><slot /></button>' },
        LoadingSpinner: { template: '<div>Loading</div>' },
        InventoryAuditFlagsPanel: true,
        InventoryAdjustmentDialog: true,
        InventoryMovementHistory: true,
        InventoryReturnsPanel: true,
      },
    },
  });
}

describe('AdminInventoryPage', () => {
  beforeEach(() => {
    get.mockImplementation((url) => {
      if (url === '/admin/inventory/summary') {
        return Promise.resolve({
          data: {
            total_products: 1,
            total_on_hand: 8,
            total_reserved: 2,
            total_committed: 1,
            total_available: 5,
            low_stock_count: 1,
            out_of_stock_count: 0,
          },
        });
      }

      return Promise.resolve(inventoryResponse());
    });
  });

  it('renders audited quantities and sends custom dropdown filters to the API', async () => {
    const wrapper = mountPage();

    await flushPromises();

    expect(wrapper.text()).toContain('Test Robot');
    expect(wrapper.text()).toContain('ROBOT-41');
    expect(wrapper.text()).toContain('Low stock');
    expect(wrapper.find('[data-label="On hand"]').text()).toBe('8');
    expect(wrapper.find('[data-label="Reserved"]').text()).toBe('2');
    expect(wrapper.find('[data-label="Committed"]').text()).toBe('1');
    expect(wrapper.find('[data-label="Available"]').text()).toBe('5');

    await wrapper.find('button[aria-label="Filter by stock status"]').trigger('click');
    await wrapper.findAll('[role="option"]').find((option) => option.text() === 'Low stock').trigger('click');
    await nextTick();
    await flushPromises();

    const filteredRequest = get.mock.calls
      .filter(([url]) => url === '/admin/inventory')
      .at(-1);
    expect(filteredRequest[1].params).toMatchObject({
      status: 'low_stock',
      low_stock: 1,
      page: 1,
    });

    await wrapper.find('button[aria-label="Rows per page"]').trigger('click');
    await wrapper.findAll('[role="option"]').find((option) => option.text() === '50 per page').trigger('click');
    await nextTick();
    await flushPromises();

    const paginatedRequest = get.mock.calls
      .filter(([url]) => url === '/admin/inventory')
      .at(-1);
    expect(paginatedRequest[1].params).toMatchObject({
      per_page: 50,
      page: 1,
    });
  });

  it('exposes responsive stock structure, bulk actions, and accessible section tabs', async () => {
    const wrapper = mountPage();
    await flushPromises();

    expect(wrapper.find('.inventory-table-wrap').attributes('tabindex')).toBe('0');
    expect(wrapper.find('.inventory-table__row').exists()).toBe(true);
    expect(wrapper.findAll('.inventory-table__metric')).toHaveLength(4);
    expect(wrapper.findAll('.inventory-tabs button')[0].attributes('aria-current')).toBe('page');

    await wrapper.find('tbody input[type="checkbox"]').setValue(true);
    expect(wrapper.find('.inventory-mobile-bulk').text()).toContain('1 selected');
    expect(wrapper.text()).toContain('Adjust selected (1)');

    await wrapper.findAll('.inventory-tabs button')[1].trigger('click');
    expect(wrapper.find('inventory-returns-panel-stub').exists()).toBe(true);
    expect(wrapper.find('.inventory-mobile-bulk').exists()).toBe(false);
    expect(wrapper.findAll('.inventory-tabs button')[1].attributes('aria-current')).toBe('page');

    await wrapper.findAll('.inventory-tabs button')[2].trigger('click');
    expect(wrapper.find('inventory-audit-flags-panel-stub').exists()).toBe(true);
    expect(wrapper.findAll('.inventory-tabs button')[2].attributes('aria-current')).toBe('page');
  });
});
