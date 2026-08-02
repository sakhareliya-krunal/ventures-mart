function absoluteSeoUrl(path) {
  if (!path) {
    return typeof window !== 'undefined' ? `${window.location.origin}/` : '';
  }

  if (/^https?:\/\//i.test(path)) {
    return path;
  }

  const origin = typeof window !== 'undefined' ? window.location.origin : '';
  return `${origin}/${String(path).replace(/^\//, '')}`;
}

export function seoHeadFromRecord(record, fallback = {}) {
  const metadata = record?.seo?.metadata || {};
  const title = metadata.title || fallback.title || 'Ventures Mart';
  const description = metadata.meta_description || fallback.description || '';
  const canonical = absoluteSeoUrl(metadata.canonical_url || fallback.canonical || '');
  const image = absoluteSeoUrl(metadata.og_image || metadata.twitter_image || fallback.image || '');
  const robots = metadata.meta_robots || fallback.robots || 'index,follow';

  return {
    title,
    meta: [
      description ? { name: 'description', content: description } : null,
      { name: 'robots', content: robots },
      { property: 'og:title', content: metadata.og_title || title },
      description ? { property: 'og:description', content: metadata.og_description || description } : null,
      image ? { property: 'og:image', content: image } : null,
      { name: 'twitter:card', content: 'summary_large_image' },
      { name: 'twitter:title', content: metadata.twitter_title || metadata.og_title || title },
      description ? { name: 'twitter:description', content: metadata.twitter_description || metadata.og_description || description } : null,
      image ? { name: 'twitter:image', content: image } : null,
    ].filter(Boolean),
    link: canonical ? [{ rel: 'canonical', href: canonical }] : [],
  };
}

/**
 * Apply SEO payload injected by Laravel into window.__APP__.seo.
 */
export function seoHeadFromServer(fallback = {}) {
  const server = typeof window !== 'undefined' ? window.__APP__?.seo || {} : {};
  const title = server.title || fallback.title || 'Ventures Mart';
  const description = server.description || fallback.description || '';
  const canonical = absoluteSeoUrl(server.canonical || fallback.canonical || '');
  const image = absoluteSeoUrl(server.og?.image || server.twitter?.image || fallback.image || '');
  const robots = server.robots || fallback.robots || 'index,follow';

  return {
    title,
    meta: [
      description ? { name: 'description', content: description } : null,
      { name: 'robots', content: robots },
      { property: 'og:site_name', content: server.og?.site_name || server.brand_name || 'Ventures Mart' },
      { property: 'og:type', content: server.og?.type || 'website' },
      server.og?.locale || server.og_locale
        ? { property: 'og:locale', content: server.og?.locale || server.og_locale }
        : null,
      { property: 'og:title', content: server.og?.title || title },
      description ? { property: 'og:description', content: server.og?.description || description } : null,
      server.og?.url || canonical ? { property: 'og:url', content: server.og?.url || canonical } : null,
      image ? { property: 'og:image', content: image } : null,
      { name: 'twitter:card', content: server.twitter?.card || 'summary_large_image' },
      { name: 'twitter:title', content: server.twitter?.title || title },
      description ? { name: 'twitter:description', content: server.twitter?.description || description } : null,
      image ? { name: 'twitter:image', content: image } : null,
    ].filter(Boolean),
    link: canonical ? [{ rel: 'canonical', href: canonical }] : [],
  };
}
