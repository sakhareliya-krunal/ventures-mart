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

export function safePublicImageUrl(value) {
  let image = String(value || '').trim();
  if (!image) return '';

  // Normalize bare public paths so they never resolve under /blog/...
  if (/^(storage|images|products|uploads)\//i.test(image)) {
    image = `/${image}`;
  }

  if (/^\/(?!\/)/.test(image)) {
    return image;
  }

  try {
    const url = new URL(image);
    if (url.protocol === 'https:') {
      return url.href;
    }

    if (
      url.protocol === 'http:' &&
      typeof window !== 'undefined' &&
      window.location.protocol === 'http:' &&
      url.origin === window.location.origin
    ) {
      return url.href;
    }
  } catch {
    return '';
  }

  return '';
}

export function seoHeadFromRecord(record, fallback = {}) {
  const metadata = record?.seo?.metadata || {};
  const title = metadata.title || fallback.title || 'Ventures Mart';
  const description = metadata.meta_description || fallback.description || '';
  const keywords = metadata.meta_keywords || fallback.keywords || '';
  const canonical = absoluteSeoUrl(metadata.canonical_url || fallback.canonical || '');
  const image = absoluteSeoUrl(metadata.og_image || metadata.twitter_image || fallback.image || '');
  const robots = metadata.meta_robots || fallback.robots || 'index,follow';
  const siteName = fallback.siteName || 'Ventures Mart';

  return {
    title,
    meta: [
      description ? { name: 'description', content: description } : null,
      keywords ? { name: 'keywords', content: keywords } : null,
      { name: 'robots', content: robots },
      { property: 'og:site_name', content: siteName },
      { property: 'og:type', content: fallback.ogType || 'website' },
      { property: 'og:locale', content: fallback.ogLocale || 'en_IN' },
      { property: 'og:title', content: metadata.og_title || title },
      description ? { property: 'og:description', content: metadata.og_description || description } : null,
      canonical ? { property: 'og:url', content: canonical } : null,
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
 * Apply SEO payload from Laravel (window.__APP__.seo or /api/seo).
 */
export function seoHeadFromServer(fallback = {}, serverOverride = null) {
  const server =
    serverOverride ||
    (typeof window !== 'undefined' ? window.__APP__?.seo || {} : {});
  const title = server.title || fallback.title || 'Ventures Mart';
  const description = server.description || fallback.description || '';
  const keywords = server.keywords || fallback.keywords || '';
  const canonical = absoluteSeoUrl(server.canonical || fallback.canonical || '');
  const image = absoluteSeoUrl(server.og?.image || server.twitter?.image || fallback.image || '');
  const robots = server.robots || fallback.robots || 'index,follow';

  return {
    title,
    meta: [
      description ? { name: 'description', content: description } : null,
      keywords ? { name: 'keywords', content: keywords } : null,
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

const JSON_LD_ATTR = 'data-vm-jsonld';

export function applyJsonLdScripts(schemas = []) {
  if (typeof document === 'undefined') {
    return;
  }

  document.querySelectorAll(`script[${JSON_LD_ATTR}]`).forEach((node) => node.remove());

  (Array.isArray(schemas) ? schemas : []).filter(Boolean).forEach((schema) => {
    const script = document.createElement('script');
    script.type = 'application/ld+json';
    script.setAttribute(JSON_LD_ATTR, '1');
    script.textContent = JSON.stringify(schema);
    document.head.appendChild(script);
  });
}

export async function syncSeoForPath(path, { applyHead } = {}) {
  if (typeof window === 'undefined') {
    return null;
  }

  const normalized = path || '/';
  try {
    const response = await fetch(`/api/seo?path=${encodeURIComponent(normalized)}`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    });
    if (!response.ok) {
      return null;
    }
    const seo = await response.json();
    if (window.__APP__) {
      window.__APP__.seo = seo;
    }
    applyJsonLdScripts(seo.json_ld || []);
    if (typeof applyHead === 'function') {
      applyHead(seoHeadFromServer({}, seo));
    }
    return seo;
  } catch {
    return null;
  }
}
