import { afterEach, describe, expect, it } from 'vitest';
import { seoHeadFromServer } from './seoHead';

describe('SPA SEO navigation', () => {
  afterEach(() => {
    delete window.__APP__;
  });

  it('does not reuse initial server metadata after navigating to another path', () => {
    window.__APP__ = {
      seo: {
        title: 'Login | Ventures Mart',
        canonical: `${window.location.origin}/login`,
      },
    };

    const head = seoHeadFromServer({
      title: 'Ventures Mart | Home',
      canonical: '/',
    });

    expect(head.title).toBe('Ventures Mart | Home');
    expect(head.link[0].href).toBe(`${window.location.origin}/`);
  });

  it('keeps matching server metadata on the initial route', () => {
    window.__APP__ = {
      seo: {
        title: 'Configured home title',
        canonical: `${window.location.origin}/`,
      },
    };

    expect(seoHeadFromServer({ title: 'Fallback', canonical: '/' }).title)
      .toBe('Configured home title');
  });
});
