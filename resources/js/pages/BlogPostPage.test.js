import { createPinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';
import { describe, expect, it, vi } from 'vitest';
import BlogPostPage from './BlogPostPage.vue';

const { get } = vi.hoisted(() => ({ get: vi.fn() }));

vi.mock('@/services/api', () => ({
  default: { get },
}));

vi.mock('@unhead/vue', () => ({
  useHead: vi.fn(),
}));

describe('BlogPostPage', () => {
  it('renders a readable article with section navigation and related cards', async () => {
    get.mockResolvedValue({
      data: {
        data: {
          id: 1,
          slug: 'premium-guide',
          title: 'Premium family guide',
          excerpt: 'A thoughtful guide for families.',
          body: '<p>Introduction copy.</p><h2>Choosing well</h2><p>Choose with care.</p><h2>Using daily</h2><p>Make it useful.</p>',
          cover_image: null,
          published_at: '2026-08-09T08:00:00.000Z',
          seo: {},
        },
        related: [
          {
            id: 2,
            slug: 'related',
            title: 'Related story',
            excerpt: 'More ideas.',
            cover_image: null,
          },
        ],
      },
    });

    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/blog/:slug', component: { template: '<div />' } },
        { path: '/blog', component: { template: '<div />' } },
      ],
    });
    await router.push('/blog/premium-guide');
    await router.isReady();

    const wrapper = mount(BlogPostPage, {
      global: { plugins: [createPinia(), router] },
    });
    await flushPromises();

    expect(wrapper.find('.breadcrumb').exists()).toBe(false);
    expect(wrapper.find('h1').text()).toBe('Premium family guide');
    expect(wrapper.find('.article-premium__toc').text()).toContain('Choosing well');
    expect(wrapper.find('.article-premium__toc').text()).toContain('Using daily');
    expect(wrapper.find('.article-premium__reading-time').text()).toContain('min read');
    expect(wrapper.find('.article-premium .button--primary').exists()).toBe(true);
    expect(wrapper.find('.blog-grid').text()).toContain('Related story');
  });
});
