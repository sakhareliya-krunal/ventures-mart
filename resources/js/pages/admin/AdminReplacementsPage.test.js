import { flushPromises, mount } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AdminReplacementsPage from './AdminReplacementsPage.vue';

const { get, post } = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
}));

vi.mock('@/services/api', () => ({
  default: { get, post },
}));

function row(overrides = {}) {
  return {
    id: 3,
    status: 'requested',
    reason: 'damaged',
    notes: 'Broken hinge',
    requested_at: '2026-08-09T10:00:00+05:30',
    order: {
      id: 11,
      number: 'VM-ORIG-1',
      email: 'buyer@example.com',
      full_name: 'Buyer',
      status: 'Delivered',
    },
    replacement_order: null,
    ...overrides,
  };
}

async function mountPage() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/admin/replacements', component: AdminReplacementsPage }],
  });
  await router.push('/admin/replacements');
  await router.isReady();

  return mount(AdminReplacementsPage, {
    global: {
      plugins: [router],
      stubs: {
        RouterLink: {
          props: ['to'],
          template: '<a :href="typeof to === \'string\' ? to : \'#\'"><slot /></a>',
        },
        AppButton: {
          props: ['disabled', 'loading'],
          emits: ['click'],
          template: '<button :disabled="disabled || loading" @click="$emit(\'click\')"><slot /></button>',
        },
        AppSelect: {
          props: ['modelValue', 'options'],
          emits: ['update:modelValue'],
          template: '<select :value="modelValue" @change="$emit(\'update:modelValue\', $event.target.value)"></select>',
        },
        AdminSearchField: {
          props: ['modelValue'],
          emits: ['update:modelValue'],
          template: '<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
        },
        AdminPagination: true,
      },
    },
  });
}

describe('AdminReplacementsPage', () => {
  beforeEach(() => {
    get.mockReset();
    post.mockReset();
    get.mockResolvedValue({
      data: {
        data: [row()],
        meta: { current_page: 1, last_page: 1, total: 1, from: 1, to: 1 },
      },
    });
  });

  it('loads replacement requests and approves them', async () => {
    post.mockResolvedValue({
      data: {
        data: row({
          status: 'fulfilled',
          replacement_order: { id: 22, number: 'VM-R-ABC1234' },
        }),
      },
    });

    const wrapper = await mountPage();
    await flushPromises();

    expect(get).toHaveBeenCalledWith('/admin/replacement-requests', expect.objectContaining({
      params: expect.objectContaining({ page: 1, per_page: 20 }),
    }));
    expect(wrapper.text()).toContain('VM-ORIG-1');
    expect(wrapper.text()).toContain('damaged');

    const approve = wrapper.findAll('button').find((button) =>
      button.text().includes('Approve'));
    await approve.trigger('click');
    await flushPromises();

    expect(post).toHaveBeenCalledWith('/admin/replacement-requests/3/approve');
  });

  it('rejects a replacement request with a reason', async () => {
    window.prompt = vi.fn(() => 'Photos unclear');
    post.mockResolvedValue({
      data: { data: row({ status: 'rejected', rejection_reason: 'Photos unclear' }) },
    });

    const wrapper = await mountPage();
    await flushPromises();

    const reject = wrapper.findAll('button').find((button) =>
      button.text().includes('Reject'));
    await reject.trigger('click');
    await flushPromises();

    expect(post).toHaveBeenCalledWith('/admin/replacement-requests/3/reject', {
      rejection_reason: 'Photos unclear',
    });
  });
});
