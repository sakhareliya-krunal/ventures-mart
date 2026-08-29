import { flushPromises, mount } from '@vue/test-utils';
import { nextTick } from 'vue';
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
        AppButton: {
          props: ['type', 'disabled'],
          template: '<button :type="type || \'button\'" :disabled="disabled"><slot /></button>',
        },
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
    Element.prototype.scrollIntoView = vi.fn();
    HTMLInputElement.prototype.focus = vi.fn();
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
        {
          id: 2,
          mobile_image: '/storage/banners/hidden-mobile.webp',
          web_image: '/storage/banners/hidden-web.webp',
          alt_text: 'Hidden banner',
          sort_order: 12,
          is_active: false,
        },
      ]),
    );
  });

  it('loads and renders existing banners', async () => {
    const wrapper = mountPage();
    await flushPromises();

    expect(get).toHaveBeenCalledWith('/admin/banners');
    const cards = wrapper.findAll('.admin-banner-card');

    expect(cards).toHaveLength(2);
    expect(cards[0].find('.admin-banner-card__order').text()).toContain('#01');
    expect(cards[0].find('.admin-banner-card__order').text()).toContain('Sort 0');
    expect(cards[0].text()).toContain('Homepage banner');
    expect(cards[0].find('.admin-banner-status').text()).toBe('Active');
    expect(cards[0].find('.admin-banner-card__preview--mobile img[src="/storage/banners/mobile.webp"]').exists()).toBe(true);
    expect(cards[0].find('.admin-banner-card__preview--web img[src="/storage/banners/web.webp"]').exists()).toBe(true);
    expect(cards[1].find('.admin-banner-card__order').text()).toContain('#02');
    expect(cards[1].find('.admin-banner-card__order').text()).toContain('Sort 12');
    expect(cards[1].find('.admin-banner-status').text()).toBe('Hidden');
    expect(wrapper.find('.admin-banner-card__paths').exists()).toBe(false);

    wrapper.unmount();
  });

  it('opens the create form in view when Add banner is clicked', async () => {
    const wrapper = mountPage();
    await flushPromises();

    await wrapper.findAll('button').find((button) => button.text().includes('Add banner')).trigger('click');
    await nextTick();

    expect(wrapper.find('.admin-banner-form-panel').exists()).toBe(true);
    expect(wrapper.find('form').exists()).toBe(true);
    expect(wrapper.find('.admin-banner-active-switch').exists()).toBe(true);
    expect(wrapper.find('.admin-banner-active-switch__text').text()).toBe('Active');
    expect(wrapper.find('.admin-banner-active-switch input[type="checkbox"]').element.checked).toBe(true);
    expect(Element.prototype.scrollIntoView).toHaveBeenCalledWith({ behavior: 'smooth', block: 'start' });
    expect(HTMLInputElement.prototype.focus).toHaveBeenCalledWith({ preventScroll: true });

    wrapper.unmount();
  });

  it('shows an uploading loader while mobile images upload', async () => {
    let resolveUpload;
    post.mockImplementation(() => new Promise((resolve) => {
      resolveUpload = resolve;
    }));
    const wrapper = mountPage();
    await flushPromises();

    await wrapper.findAll('button').find((button) => button.text().includes('Add banner')).trigger('click');
    const input = wrapper.findAll('input[type="file"]')[0];
    Object.defineProperty(input.element, 'files', {
      configurable: true,
      value: [new File(['image'], 'mobile.webp', { type: 'image/webp' })],
    });

    await input.trigger('change');
    await nextTick();

    expect(wrapper.find('.admin-banner-field.is-uploading').exists()).toBe(true);
    expect(wrapper.find('.admin-banner-field__overlay').text()).toContain('Uploading...');
    expect(wrapper.findAll('button').some((button) => button.text().includes('Uploading...'))).toBe(false);

    resolveUpload({ data: { urls: ['/storage/banners/uploaded-mobile.webp'] } });
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

  it('creates a hidden banner when the status switch is off', async () => {
    post.mockResolvedValue({ data: { data: { id: 2 } } });
    get
      .mockResolvedValueOnce(bannersResponse([]))
      .mockResolvedValueOnce(bannersResponse([{ id: 2, alt_text: 'Hidden banner' }]));
    const wrapper = mountPage();
    await flushPromises();

    await wrapper.findAll('button').find((button) => button.text().includes('Add banner')).trigger('click');
    const statusSwitch = wrapper.find('.admin-banner-active-switch input[type="checkbox"]');
    await statusSwitch.setValue(false);
    expect(wrapper.find('.admin-banner-active-switch__text').text()).toBe('Hidden');
    const inputs = wrapper.findAll('input').filter((input) => !input.attributes('type'));
    await inputs[0].setValue('Hidden banner');
    await inputs[1].setValue('/storage/banners/mobile.webp');
    await inputs[2].setValue('/storage/banners/web.webp');
    await wrapper.find('form').trigger('submit');
    await flushPromises();

    expect(post).toHaveBeenCalledWith('/admin/banners', {
      mobile_image: '/storage/banners/mobile.webp',
      web_image: '/storage/banners/web.webp',
      alt_text: 'Hidden banner',
      is_active: false,
      sort_order: 0,
    });

    wrapper.unmount();
  });
});
