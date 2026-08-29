import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import AdminSettingsPage from './AdminSettingsPage.vue';

const { get, patch, post, del } = vi.hoisted(() => ({
  get: vi.fn(),
  patch: vi.fn(),
  post: vi.fn(),
  del: vi.fn(),
}));

vi.mock('@/services/api', () => ({
  default: { get, patch, post, delete: del },
}));

vi.mock('@lucide/vue', () => {
  const Icon = { template: '<span aria-hidden="true" />' };
  return { Check: Icon, ChevronDown: Icon };
});

let wrapper;

function mountPage() {
  wrapper = mount(AdminSettingsPage, {
    attachTo: document.body,
    global: {
      stubs: {
        AdminSeoTab: { template: '<section data-test="seo-tab" />' },
        AppButton: { template: '<button><slot /></button>' },
        FormField: { template: '<label><slot /></label>' },
      },
    },
  });

  return wrapper;
}

describe('AdminSettingsPage', () => {
  afterEach(() => {
    wrapper?.unmount();
    wrapper = undefined;
  });

  beforeEach(() => {
    get.mockReset();
    patch.mockReset();
    post.mockReset();
    del.mockReset();
    get.mockImplementation((url) => {
      if (url === '/admin/seo/settings') {
        return Promise.resolve({ data: {} });
      }
      if (url.startsWith('/admin/seo/pages/')) {
        return Promise.resolve({ data: {} });
      }
      if (url === '/admin/seo/redirects') {
        return Promise.resolve({ data: { data: [] } });
      }
      return Promise.resolve({ data: {} });
    });
  });

  it('uses the custom dropdown for the page SEO selector and closes after selection', async () => {
    wrapper = mountPage();
    await flushPromises();

    const pageSelect = wrapper.find('.admin-toolbar .app-select');
    const trigger = pageSelect.find('.app-select__trigger');
    await trigger.trigger('click');

    expect(trigger.attributes('aria-expanded')).toBe('true');

    const shopOption = pageSelect.findAll('[role="option"]').find((option) => option.text().includes('Shop'));
    await shopOption.trigger('click');
    await flushPromises();

    expect(trigger.text()).toContain('Shop');
    expect(trigger.attributes('aria-expanded')).toBe('false');
    expect(get).toHaveBeenCalledWith('/admin/seo/pages/shop');
  });
});
