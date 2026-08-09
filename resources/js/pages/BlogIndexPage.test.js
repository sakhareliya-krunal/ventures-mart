import { createPinia } from 'pinia';
import { flushPromises, mount, RouterLinkStub } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import BlogIndexPage from './BlogIndexPage.vue';

const { get } = vi.hoisted(() => ({ get: vi.fn() }));

vi.mock('@/services/api', () => ({
  default: { get },
}));

vi.mock('@unhead/vue', () => ({
  useHead: vi.fn(),
}));

const posts = [
  {
    id: 1,
    slug: 'featured',
    title: 'Featured family story',
    excerpt: 'A useful family guide.',
    cover_image: '/images/featured.jpg',
    published_at: '2026-08-09T08:00:00.000Z',
  },
  {
    id: 2,
    slug: 'latest',
    title: 'Latest lunch guide',
    excerpt: 'A practical lunch guide.',
    cover_image: null,
    published_at: '2026-08-08T08:00:00.000Z',
  },
];

describe('BlogIndexPage', () => {
  beforeEach(() => {
    get.mockResolvedValue({ data: { data: posts } });
  });

  it('presents the first story as featured and the remainder in the editorial grid', async () => {
    const wrapper = mount(BlogIndexPage, {
      global: {
        plugins: [createPinia()],
        stubs: { RouterLink: RouterLinkStub },
      },
    });
    await flushPromises();

    expect(wrapper.find('.blog-card--featured').text()).toContain('Featured family story');
    expect(wrapper.find('.blog-grid').text()).toContain('Latest lunch guide');
    expect(wrapper.text()).toContain('2 stories for your family');
  });

  it('shows a retry action when posts cannot be loaded', async () => {
    get.mockRejectedValueOnce(new Error('Offline'));
    const wrapper = mount(BlogIndexPage, {
      global: {
        plugins: [createPinia()],
        stubs: { RouterLink: RouterLinkStub },
      },
    });
    await flushPromises();

    expect(wrapper.find('.blog-index__error').exists()).toBe(true);
    expect(wrapper.text()).toContain('Try again');
  });
});
