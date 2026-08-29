import { flushPromises, mount, RouterLinkStub } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AdminDashboardPage from './AdminDashboardPage.vue';

const { get } = vi.hoisted(() => ({ get: vi.fn() }));

vi.mock('@/services/api', () => ({
  default: { get },
}));

vi.mock('@lucide/vue', () => {
  const Icon = { template: '<span aria-hidden="true" />' };
  return {
    FileText: Icon,
    Mail: Icon,
    Package: Icon,
    ShoppingBag: Icon,
    TrendingUp: Icon,
    Users: Icon,
  };
});

function daySeries() {
  return Array.from({ length: 24 }, (_, hour) => {
    const periodHour = hour % 12 || 12;
    const period = hour < 12 ? 'AM' : 'PM';

    return {
      key: String(hour).padStart(2, '0'),
      label: `${periodHour} ${period}`,
      total: 0,
      orders: [],
    };
  });
}

function dashboardResponse() {
  const series = daySeries();
  series[9] = {
    ...series[9],
    total: 350,
    orders: [
      {
        id: 41,
        number: 'VM-MORNING-1',
        created_at: '2026-08-09T03:45:05+00:00',
        created_at_display: '9:15:05 AM',
        total: 150,
      },
      {
        id: 42,
        number: 'VM-MORNING-2',
        created_at: '2026-08-09T04:17:30+00:00',
        created_at_display: '9:47:30 AM',
        total: 200,
      },
    ],
  };
  series[15] = {
    ...series[15],
    total: 500,
    orders: [{
      id: 43,
      number: 'VM-AFTERNOON-1',
      created_at: '2026-08-09T09:38:12+00:00',
      created_at_display: '3:08:12 PM',
      total: 500,
    }],
  };

  return {
    data: {
      revenue_range: 'day',
      revenue_period_label: 'Today',
      revenue_period_total: 850,
      revenue_period_orders: 3,
      revenue_series: series,
      revenue_last_7_days: [],
      orders_by_status: {},
      low_stock_products: [],
      recent_messages: [],
      recent_posts: [],
      recent_orders: [],
    },
  };
}

function mountPage() {
  return mount(AdminDashboardPage, {
    global: {
      stubs: {
        LoadingSpinner: { template: '<div>Loading</div>' },
        RouterLink: RouterLinkStub,
      },
    },
  });
}

describe('AdminDashboardPage day sales chart', () => {
  beforeEach(() => {
    get.mockResolvedValue(dashboardResponse());
  });

  it('splits Day into AM and PM rows and shows every exact order on hover', async () => {
    const wrapper = mountPage();
    await flushPromises();

    const groups = wrapper.findAll('.admin-dash-day__group');
    expect(groups).toHaveLength(2);
    expect(groups[0].find('.admin-dash-day__heading').text()).toContain('AM');
    expect(groups[0].find('.admin-dash-day__heading').text()).toContain('12 AM–11 AM');
    expect(groups[1].find('.admin-dash-day__heading').text()).toContain('PM');
    expect(groups[1].find('.admin-dash-day__heading').text()).toContain('12 PM–11 PM');
    expect(groups[0].findAll('.admin-dash-bar')).toHaveLength(12);
    expect(groups[1].findAll('.admin-dash-bar')).toHaveLength(12);

    await groups[0].findAll('.admin-dash-bar')[9].trigger('mouseenter');

    const tooltip = wrapper.find('.admin-dash-hour-tooltip');
    expect(tooltip.text()).toContain('9 AM');
    expect(tooltip.text()).toContain('2 orders');
    expect(tooltip.text()).toContain('VM-MORNING-1');
    expect(tooltip.text()).toContain('VM-MORNING-2');
    expect(tooltip.findAll('.admin-dash-hour-tooltip__order')).toHaveLength(2);
    expect(tooltip.find('time').attributes('datetime')).toBe('2026-08-09T03:45:05+00:00');

    expect(tooltip.text()).toContain('9:15:05 AM');
    expect(tooltip.text()).toContain('9:47:30 AM');
    expect(tooltip.findComponent(RouterLinkStub).props('to')).toBe('/admin/orders/41');
  });
});
