import { isEmptyHtml } from '@/utils/html';

export function blankPostForm() {
  return {
    title: '',
    slug: '',
    excerpt: '',
    body: '',
    cover_image: '',
    published_at: '',
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
  return errors;
}

export function buildPostPayload(form) {
  return {
    title: String(form.title || '').trim(),
    slug: String(form.slug || '').trim() || null,
    excerpt: String(form.excerpt || '').trim(),
    body: String(form.body || '').trim(),
    cover_image: String(form.cover_image || '').trim() || null,
    published_at: fromDatetimeLocal(form.published_at),
  };
}

export function apiErrorMessage(err, fallback) {
  return (
    err.response?.data?.message ||
    Object.values(err.response?.data?.errors || {})[0]?.[0] ||
    fallback
  );
}
