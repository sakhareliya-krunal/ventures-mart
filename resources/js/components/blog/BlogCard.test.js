import { mount, RouterLinkStub } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import BlogCard from './BlogCard.vue';

const post = {
  id: 1,
  slug: 'family-guide',
  title: 'A practical family guide',
  excerpt: 'Helpful ideas for everyday family routines.',
  cover_image: '/images/blog/guide.jpg',
  published_at: '2026-08-09T08:00:00.000Z',
};

describe('BlogCard', () => {
  it('renders an accessible featured editorial card', () => {
    const wrapper = mount(BlogCard, {
      props: { post, featured: true },
      global: { stubs: { RouterLink: RouterLinkStub } },
    });

    expect(wrapper.classes()).toContain('blog-card--featured');
    expect(wrapper.text()).toContain('Featured story');
    expect(wrapper.text()).toContain(post.title);
    expect(wrapper.find('img').attributes('alt')).toBe(post.title);
    expect(wrapper.findComponent(RouterLinkStub).props('to')).toBe(`/blog/${post.slug}`);
  });

  it('uses a branded fallback when no cover is available', () => {
    const wrapper = mount(BlogCard, {
      props: { post: { ...post, cover_image: null } },
      global: { stubs: { RouterLink: RouterLinkStub } },
    });

    expect(wrapper.find('.blog-card__media-fallback').exists()).toBe(true);
  });
});
