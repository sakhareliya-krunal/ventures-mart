import { createPinia, setActivePinia } from 'pinia';
import { mount, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AdminContactsPage from './AdminContactsPage.vue';
import { useAdminNavigationCountsStore } from '@/stores/adminNavigationCounts';

const { get, patch, remove } = vi.hoisted(() => ({
  get: vi.fn(),
  patch: vi.fn(),
  remove: vi.fn(),
}));

vi.mock('@/services/api', () => ({
  default: {
    get,
    patch,
    delete: remove,
  },
}));

const unreadMessage = {
  id: 1,
  name: 'Asha Shah',
  email: 'asha@example.com',
  message: 'Please share an update about my order.',
  created_at: '2026-08-09T08:00:00.000Z',
  read_at: null,
  is_read: false,
};

function listResponse(messages = [unreadMessage], unreadCount = 1) {
  return {
    data: {
      data: messages,
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 20,
        total: messages.length,
        from: messages.length ? 1 : null,
        to: messages.length || null,
        unread_count: unreadCount,
      },
    },
  };
}

describe('AdminContactsPage', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    get.mockResolvedValue(listResponse());
    patch.mockResolvedValue({
      data: {
        data: { ...unreadMessage, is_read: true, read_at: '2026-08-09T09:00:00.000Z' },
        unread_count: 0,
      },
    });
    remove.mockResolvedValue({ data: { unread_count: 0 } });
  });

  it('renders the responsive inbox and marks an opened message as read', async () => {
    const wrapper = mount(AdminContactsPage, {
      global: { plugins: [createPinia()] },
    });
    await flushPromises();

    expect(wrapper.find('.contact-inbox__shell').exists()).toBe(true);
    expect(wrapper.find('.contact-message-card--unread').exists()).toBe(true);

    await wrapper.find('.contact-message-card__open').trigger('click');
    await flushPromises();

    expect(patch).toHaveBeenCalledWith('/admin/contact-messages/1/read');
    expect(wrapper.find('.contact-inbox__detail--open').text()).toContain('Asha Shah');
    expect(wrapper.find('.contact-message-card--unread').exists()).toBe(false);
    expect(useAdminNavigationCountsStore().contactUnread).toBe(0);
  });

  it('marks every message read from the inbox action', async () => {
    const wrapper = mount(AdminContactsPage, {
      global: { plugins: [createPinia()] },
    });
    await flushPromises();

    const markAll = wrapper.findAll('button').find((button) => button.text().includes('Mark all read'));
    await markAll.trigger('click');
    await flushPromises();

    expect(patch).toHaveBeenCalledWith('/admin/contact-messages/read-all');
    expect(wrapper.find('.contact-message-card--unread').exists()).toBe(false);
  });

  it('shows a useful empty search state', async () => {
    get.mockResolvedValue(listResponse([], 0));
    const wrapper = mount(AdminContactsPage, {
      global: { plugins: [createPinia()] },
    });
    await flushPromises();

    await wrapper.find('input[type="search"]').setValue('missing');
    await new Promise((resolve) => window.setTimeout(resolve, 375));
    await flushPromises();

    expect(get).toHaveBeenLastCalledWith('/admin/contact-messages', {
      params: {
        page: 1,
        per_page: 20,
        search: 'missing',
      },
    });
    expect(wrapper.text()).toContain('No matching messages');
  });
});
