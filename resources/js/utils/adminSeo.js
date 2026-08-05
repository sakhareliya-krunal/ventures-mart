export function blankSeoFields() {
  return {
    title: '',
    meta_description: '',
    meta_keywords: '',
    focus_keyword: '',
    seo_slug: '',
    canonical_url: '',
    meta_robots: 'index,follow',
    og_title: '',
    og_description: '',
    og_image: '',
    twitter_title: '',
    twitter_description: '',
    twitter_image: '',
    image_alt_text: '',
    ai_summary: '',
    ai_highlights_text: '',
    custom_schema_text: '',
    locale: 'en-IN',
  };
}

export function blankFaq() {
  return {
    question: '',
    answer: '',
    sort_order: 0,
    is_visible: true,
  };
}

export function fillSeoFields(form, record = {}) {
  const seo = record.seo?.metadata || record.seo || {};
  Object.assign(form.seo, {
    ...blankSeoFields(),
    ...seo,
    ai_highlights_text: Array.isArray(seo.ai_highlights) ? seo.ai_highlights.join('\n') : '',
    custom_schema_text: seo.custom_schema ? JSON.stringify(seo.custom_schema, null, 2) : '',
  });
  form.faqs = Array.isArray(record.seo?.faqs) && record.seo.faqs.length
    ? record.seo.faqs.map((faq, index) => ({
        question: faq.question || '',
        answer: faq.answer || '',
        sort_order: faq.sort_order ?? index,
        is_visible: faq.is_visible !== false,
      }))
    : [];
  form.seo_score = record.seo?.score || 0;
  form.seo_checks = Array.isArray(record.seo?.checks) ? record.seo.checks : [];
  form.suggested_links = record.seo?.suggested_links || [];
}

export function buildSeoPayload(form) {
  const schemaText = String(form.seo?.custom_schema_text || '').trim();
  let customSchema = null;
  if (schemaText) {
    customSchema = JSON.parse(schemaText);
  }

  const entitySlug = String(form.slug || form.seo?.seo_slug || '').trim();

  return {
    seo: {
      title: emptyToNull(form.seo.title),
      meta_description: emptyToNull(form.seo.meta_description),
      meta_keywords: emptyToNull(form.seo.meta_keywords),
      focus_keyword: emptyToNull(form.seo.focus_keyword),
      seo_slug: emptyToNull(entitySlug),
      canonical_url: emptyToNull(form.seo.canonical_url),
      meta_robots: emptyToNull(form.seo.meta_robots) || 'index,follow',
      og_title: emptyToNull(form.seo.og_title),
      og_description: emptyToNull(form.seo.og_description),
      og_image: emptyToNull(form.seo.og_image),
      twitter_title: emptyToNull(form.seo.twitter_title),
      twitter_description: emptyToNull(form.seo.twitter_description),
      twitter_image: emptyToNull(form.seo.twitter_image),
      image_alt_text: emptyToNull(form.seo.image_alt_text),
      ai_summary: emptyToNull(form.seo.ai_summary),
      ai_highlights: String(form.seo.ai_highlights_text || '')
        .split('\n')
        .map((item) => item.trim())
        .filter(Boolean),
      custom_schema: customSchema,
      locale: emptyToNull(form.seo.locale) || 'en-IN',
    },
    faqs: (form.faqs || [])
      .filter((faq) => String(faq.question || '').trim() || String(faq.answer || '').trim())
      .map((faq, index) => ({
        question: String(faq.question || '').trim(),
        answer: String(faq.answer || '').trim(),
        sort_order: faq.sort_order ?? index,
        is_visible: faq.is_visible !== false,
      })),
  };
}

export function validateSeoFields(form) {
  const errors = {};
  const schemaText = String(form.seo?.custom_schema_text || '').trim();
  if (schemaText) {
    try {
      JSON.parse(schemaText);
    } catch {
      errors['seo.custom_schema'] = ['Custom structured data must be valid JSON.'];
    }
  }

  const canonical = String(form.seo?.canonical_url || '').trim();
  if (canonical && !/^(https?:\/\/|\/).+/i.test(canonical)) {
    errors['seo.canonical_url'] = ['Canonical URL must start with / or http(s)://.'];
  }

  return errors;
}

export function seoCharCount(value) {
  return String(value ?? '').length;
}

export function seoLengthTone(length, { min, max, hardMax }) {
  if (hardMax && length > hardMax) return 'danger';
  if (length === 0) return 'muted';
  if (length < min || length > max) return 'warn';
  return 'ok';
}

function emptyToNull(value) {
  const trimmed = String(value ?? '').trim();
  return trimmed === '' ? null : trimmed;
}
