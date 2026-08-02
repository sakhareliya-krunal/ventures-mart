import { isEmptyHtml } from '@/utils/html';
import { blankSeoFields, buildSeoPayload, fillSeoFields, validateSeoFields } from '@/utils/adminSeo';

export function blankPostForm() {
  return {
    title: '',
    slug: '',
    excerpt: '',
    body: '',
    cover_image: '',
    published_at: '',
    seo: blankSeoFields(),
    faqs: [],
    seo_score: 0,
    suggested_links: [],
  };
}

function pad(value) {
  return String(value).padStart(2, '0');
}

export function toDatetimeLocal(value) {
  if (!value) return '';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export function fromDatetimeLocal(value) {
  const trimmed = String(value || '').trim();
  if (!trimmed) return null;
  const date = new Date(trimmed);
  if (Number.isNaN(date.getTime())) return null;
  return trimmed;
}

export function validatePostForm(form) {
  const errors = {};
  if (!String(form.title || '').trim()) errors.title = ['Title is required.'];
  if (!String(form.excerpt || '').trim()) errors.excerpt = ['Excerpt is required.'];
  if (isEmptyHtml(form.body)) errors.body = ['Body is required.'];
  if (form.published_at && fromDatetimeLocal(form.published_at) === null) {
    errors.published_at = ['Enter a valid publish date and time.'];
  }
  return { ...errors, ...validateSeoFields(form) };
}

export function buildPostPayload(form) {
  return {
    title: String(form.title || '').trim(),
    slug: String(form.slug || '').trim() || null,
    excerpt: String(form.excerpt || '').trim(),
    body: String(form.body || '').trim(),
    cover_image: String(form.cover_image || '').trim() || null,
    published_at: fromDatetimeLocal(form.published_at),
    ...buildSeoPayload(form),
  };
}

export function fillPostForm(form, post) {
  Object.assign(form, {
    title: post.title || '',
    slug: post.slug || '',
    excerpt: post.excerpt || '',
    body: post.body || '',
    cover_image: post.cover_image || '',
    published_at: toDatetimeLocal(post.published_at),
  });
  fillSeoFields(form, post);
}

export function apiErrorMessage(err, fallback) {
  return (
    err.response?.data?.message ||
    Object.values(err.response?.data?.errors || {})[0]?.[0] ||
    fallback
  );
}
