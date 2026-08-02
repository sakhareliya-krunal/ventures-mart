export function blankSeoFields() {
  return {
    title: '',
    meta_description: '',
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
  form.suggested_links = record.seo?.suggested_links || [];
}

export function buildSeoPayload(form) {
  const schemaText = String(form.seo?.custom_schema_text || '').trim();
  let customSchema = null;
  if (schemaText) {
    customSchema = JSON.parse(schemaText);
  }

  return {
    seo: {
      title: emptyToNull(form.seo.title),
      meta_description: emptyToNull(form.seo.meta_description),
      focus_keyword: emptyToNull(form.seo.focus_keyword),
      seo_slug: emptyToNull(form.seo.seo_slug),
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
  return errors;
}

function emptyToNull(value) {
  const trimmed = String(value ?? '').trim();
  return trimmed === '' ? null : trimmed;
}
