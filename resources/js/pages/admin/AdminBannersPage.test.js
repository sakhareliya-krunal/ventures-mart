import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AdminBannersPage from './AdminBannersPage.vue';

const { get, post, put, remove } = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  put: vi.fn(),
  remove: vi.fn(),
}));

vi.mock('@/services/api', () => ({
  default: {
    get,
    post,
    put,
    delete: remove,
  },
}));

vi.mock('@lucide/vue', () => {
  const Icon = { template: '<span aria-hidden="true" />' };
  return {
    ChevronDown: Icon,
    ChevronUp: Icon,
    ImagePlus: Icon,
    Trash2: Icon,
  };
});

function bannersResponse(items = []) {
  return { data: { data: items } };
}

function mountPage() {
  return mount(AdminBannersPage, {
    global: {
      stubs: {
        AppButton: { template: '<button type="button"><slot /></button>' },
        ConfirmDialog: true,
        LoadingSpinner: { template: '<div />' },
      },
    },
  });
}

describe('AdminBannersPage', () => {
  beforeEach(() => {
    get.mockReset();
    post.mockReset();
    put.mockReset();
    remove.mockReset();
    get.mockResolvedValue(
      bannersResponse([
        {
          id: 1,
          mobile_image: '/storage/banners/mobile.webp',
          web_image: '/storage/banners/web.webp',
          alt_text: 'Homepage banner',
          sort_order: 0,
          is_active: true,
        },
      ]),
    );
  });

  it('loads and renders existing banners', async () => {
    const wrapper = mountPage();
    await flushPromises();

    expect(get).toHaveBeenCalledWith('/admin/banners');
    expect(wrapper.text()).toContain('Homepage banner');
    expect(wrapper.find('img[src="/storage/banners/mobile.webp"]').exists()).toBe(true);

    wrapper.unmount();
  });

  it('uploads mobile images with the banner purpose', async () => {
    post.mockResolvedValue({ data: { urls: ['/storage/banners/uploaded-mobile.webp'] } });
    const wrapper = mountPage();
    await flushPromises();

    await wrapper.findAll('button').find((button) => button.text().includes('Add banner')).trigger('click');
    const input = wrapper.findAll('input[type="file"]')[0];
    Object.defineProperty(input.element, 'files', {
      configurable: true,
      value: [new File(['image'], 'mobile.webp', { type: 'image/webp' })],
    });

    await input.trigger('change');
    await flushPromises();

    expect(post).toHaveBeenCalledWith('/admin/uploads/images', expect.any(FormData));
    expect(wrapper.find('img[src="/storage/banners/uploaded-mobile.webp"]').exists()).toBe(true);

    wrapper.unmount();
  });

  it('creates a banner from the form fields', async () => {
    post.mockResolvedValue({ data: { data: { id: 2 } } });
    get
      .mockResolvedValueOnce(bannersResponse([]))
      .mockResolvedValueOnce(bannersResponse([{ id: 2, alt_text: 'New banner' }]));
    const wrapper = mountPage();
    await flushPromises();

    await wrapper.findAll('button').find((button) => button.text().includes('Add banner')).trigger('click');
    const inputs = wrapper.findAll('input').filter((input) => !input.attributes('type'));
    await inputs[0].setValue('New banner');
    await inputs[1].setValue('/storage/banners/mobile.webp');
    await inputs[2].setValue('/storage/banners/web.webp');
    await wrapper.find('form').trigger('submit');
    await flushPromises();

    expect(post).toHaveBeenCalledWith('/admin/banners', {
      mobile_image: '/storage/banners/mobile.webp',
      web_image: '/storage/banners/web.webp',
      alt_text: 'New banner',
      is_active: true,
      sort_order: 0,
    });

    wrapper.unmount();
  });
});
