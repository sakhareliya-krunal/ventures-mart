export function seoHeadFromRecord(record, fallback = {}) {
  const metadata = record?.seo?.metadata || {};
  const title = metadata.title || fallback.title || 'Ventures Mart';
  const description = metadata.meta_description || fallback.description || '';
  const canonical = metadata.canonical_url || fallback.canonical || '';
  const image = metadata.og_image || metadata.twitter_image || fallback.image || '';

  return {
    title,
    meta: [
      description ? { name: 'description', content: description } : null,
      { name: 'robots', content: metadata.meta_robots || 'index,follow' },
      { property: 'og:title', content: metadata.og_title || title },
      description ? { property: 'og:description', content: metadata.og_description || description } : null,
      image ? { property: 'og:image', content: image } : null,
      { name: 'twitter:card', content: 'summary_large_image' },
      { name: 'twitter:title', content: metadata.twitter_title || metadata.og_title || title },
      description ? { name: 'twitter:description', content: metadata.twitter_description || metadata.og_description || description } : null,
      image ? { name: 'twitter:image', content: metadata.twitter_image || image } : null,
    ].filter(Boolean),
    link: canonical ? [{ rel: 'canonical', href: canonical }] : [],
  };
}
