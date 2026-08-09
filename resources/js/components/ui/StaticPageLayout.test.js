import { mount, RouterLinkStub } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import StaticPageLayout from './StaticPageLayout.vue';

describe('StaticPageLayout', () => {
  it('renders premium page slots and accessible section navigation', () => {
    const wrapper = mount(StaticPageLayout, {
      props: {
        eyebrow: 'Support',
        title: 'Shipping',
        lead: 'Delivery information.',
        sections: [
          { id: 'dispatch', label: 'Dispatch' },
          { id: 'tracking', label: 'Tracking' },
        ],
        wide: true,
      },
      slots: {
        actions: '<a class="button button--primary" href="/contact">Get help</a>',
        aside: '<strong>Free shipping</strong>',
        default: '<section id="dispatch">Dispatch details</section>',
        cta: '<div class="test-cta">Ready to shop</div>',
      },
      global: { stubs: { RouterLink: RouterLinkStub } },
    });

    expect(wrapper.find('.static-shell__body--wide').exists()).toBe(true);
    expect(wrapper.find('.static-shell__nav').attributes('aria-label')).toBe('On this page');
    expect(wrapper.find('.static-shell .button--primary').text()).toBe('Get help');
    expect(wrapper.find('a[href="#tracking"]').text()).toBe('Tracking');
    expect(wrapper.text()).toContain('Free shipping');
    expect(wrapper.find('.test-cta').exists()).toBe(true);
  });
});
