import { flushPromises, mount, RouterLinkStub } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ShoppingConfidencePage from './ShoppingConfidencePage.vue';

const { get } = vi.hoisted(() => ({ get: vi.fn() }));

vi.mock('@/services/api', () => ({
  default: { get },
}));

vi.mock('@unhead/vue', () => ({
  useHead: vi.fn(),
}));

vi.mock('@lucide/vue', () => {
  const Icon = { template: '<span aria-hidden="true" />' };
  return {
    ChevronRight: Icon,
    CreditCard: Icon,
    MapPin: Icon,
    RefreshCw: Icon,
    Truck: Icon,
  };
});

function mountPage() {
  return mount(ShoppingConfidencePage, {
    global: {
      plugins: [createPinia()],
      stubs: {
        AppButton: { template: '<a><slot /></a>' },
        Breadcrumb: true,
        RouterLink: RouterLinkStub,
      },
    },
  });
}

describe('ShoppingConfidencePage hero', () => {
  beforeEach(() => {
    get.mockReset();
    setActivePinia(createPinia());
    window.__APP__ = { seo: {} };
  });

  it('uses the configured Admin SEO image', async () => {
    get.mockResolvedValue({
      data: {
        page_image: 'https://cdn.example.com/confidence.webp',
      },
    });

    const wrapper = mountPage();
    await flushPromises();

    expect(get).toHaveBeenCalledWith('/seo', {
      params: { path: '/shopping-confidence-shipping-replacement' },
    });
    expect(wrapper.get('.article-premium__hero-media img').attributes('src'))
      .toBe('https://cdn.example.com/confidence.webp');
  });

  it('falls back when the configured image is unsafe or cannot load', async () => {
    get.mockResolvedValue({
      data: { page_image: 'javascript:alert(1)' },
    });

    const unsafeWrapper = mountPage();
    await flushPromises();
    expect(unsafeWrapper.get('.article-premium__hero-media img').attributes('src'))
      .toBe('/images/hero/poster.jpg');

    get.mockResolvedValue({
      data: { page_image: 'https://cdn.example.com/missing.webp' },
    });
    const missingWrapper = mountPage();
    await flushPromises();
    await missingWrapper.get('.article-premium__hero-media img').trigger('error');
    expect(missingWrapper.get('.article-premium__hero-media img').attributes('src'))
      .toBe('/images/hero/poster.jpg');
  });

  it('keeps the bundled fallback when SEO loading fails', async () => {
    get.mockRejectedValue(new Error('Network unavailable'));

    const wrapper = mountPage();
    await flushPromises();

    expect(wrapper.get('.article-premium__hero-media img').attributes('src'))
      .toBe('/images/hero/poster.jpg');
  });

  it('groups each section icon and label for responsive spacing', async () => {
    get.mockResolvedValue({ data: {} });

    const wrapper = mountPage();
    await flushPromises();

    const kickers = wrapper.findAll('.article-premium__section-kicker');
    expect(kickers).toHaveLength(4);
    kickers.forEach((kicker) => {
      expect(kicker.find('.article-premium__section-icon').exists()).toBe(true);
      expect(kicker.find('.eyebrow').exists()).toBe(true);
    });
  });
});
