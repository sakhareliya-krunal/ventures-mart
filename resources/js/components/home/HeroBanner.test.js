import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import HeroBanner from './HeroBanner.vue';

const { get } = vi.hoisted(() => ({ get: vi.fn() }));

vi.mock('@/services/api', () => ({
  default: { get },
}));

vi.mock('@lucide/vue', () => {
  const Icon = { template: '<span aria-hidden="true" />' };
  return {
    ChevronLeft: Icon,
    ChevronRight: Icon,
  };
});

describe('HeroBanner', () => {
  beforeEach(() => {
    get.mockReset();
    window.requestAnimationFrame = vi.fn((callback) => window.setTimeout(callback, 0));
    window.cancelAnimationFrame = vi.fn((id) => window.clearTimeout(id));
  });

  it('uses active admin banners for the carousel images', async () => {
    get.mockResolvedValue({
      data: {
        data: [
          {
            id: 1,
            mobile_image: '/storage/banners/mobile.webp',
            web_image: '/storage/banners/web.webp',
            alt_text: 'Festive sale banner',
          },
        ],
      },
    });

    const wrapper = mount(HeroBanner);
    await flushPromises();

    expect(get).toHaveBeenCalledWith('/banners', { skipErrorToast: true });
    expect(wrapper.get('img').attributes('src')).toBe('/storage/banners/mobile.webp');
    expect(wrapper.find('source[media="(min-width: 1025px)"]').attributes('srcset')).toBe('/storage/banners/web.webp');
    expect(wrapper.get('img').attributes('alt')).toBe('Festive sale banner');

    wrapper.unmount();
  });

  it('keeps the built-in carousel when the banner API has no usable images', async () => {
    get.mockResolvedValue({ data: { data: [] } });

    const wrapper = mount(HeroBanner);
    await flushPromises();

    expect(wrapper.get('img').attributes('src')).toContain('/images/hero/carousel/');

    wrapper.unmount();
  });
});
