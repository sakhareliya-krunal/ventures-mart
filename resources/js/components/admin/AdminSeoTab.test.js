import { flushPromises, mount } from '@vue/test-utils';
import { reactive } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AdminSeoTab from './AdminSeoTab.vue';
import { blankSeoFields } from '@/utils/adminSeo';

const { post } = vi.hoisted(() => ({ post: vi.fn() }));

vi.mock('@/services/api', () => ({
  default: { post },
}));

vi.mock('@lucide/vue', () => {
  const Icon = { template: '<span aria-hidden="true" />' };
  return {
    ImagePlus: Icon,
    Plus: Icon,
    Trash2: Icon,
  };
});

function makeForm() {
  return reactive({
    seo: blankSeoFields(),
    faqs: [],
    seo_score: 0,
    seo_checks: [],
    suggested_links: [],
  });
}

describe('AdminSeoTab image upload', () => {
  beforeEach(() => {
    post.mockReset();
  });

  it('uploads and previews the Open Graph image', async () => {
    post.mockResolvedValue({
      data: { urls: ['/storage/products/confidence.webp'] },
    });
    const form = makeForm();
    const wrapper = mount(AdminSeoTab, {
      props: { form },
      global: {
        stubs: {
          AppButton: { template: '<button type="button"><slot /></button>' },
        },
      },
    });
    const fileInput = wrapper.get('input[type="file"]');
    const file = new File(['image'], 'confidence.webp', { type: 'image/webp' });
    Object.defineProperty(fileInput.element, 'files', {
      configurable: true,
      value: [file],
    });

    await fileInput.trigger('change');
    await flushPromises();

    expect(post).toHaveBeenCalledWith('/admin/uploads/images', expect.any(FormData));
    expect(form.seo.og_image).toBe('/storage/products/confidence.webp');
    expect(wrapper.get('.admin-seo-image-preview').attributes('src'))
      .toBe('/storage/products/confidence.webp');
  });

  it('shows an upload error without replacing the existing image', async () => {
    post.mockRejectedValue({
      response: { data: { message: 'Invalid image.' } },
    });
    const form = makeForm();
    form.seo.og_image = '/images/hero/poster.jpg';
    const wrapper = mount(AdminSeoTab, {
      props: { form },
      global: {
        stubs: {
          AppButton: { template: '<button type="button"><slot /></button>' },
        },
      },
    });
    const fileInput = wrapper.get('input[type="file"]');
    Object.defineProperty(fileInput.element, 'files', {
      configurable: true,
      value: [new File(['bad'], 'bad.webp', { type: 'image/webp' })],
    });

    await fileInput.trigger('change');
    await flushPromises();

    expect(wrapper.text()).toContain('Invalid image.');
    expect(form.seo.og_image).toBe('/images/hero/poster.jpg');
  });
});
