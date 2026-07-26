import DOMPurify from 'dompurify';

const ALLOWED_TAGS = [
  'p',
  'br',
  'strong',
  'b',
  'em',
  'i',
  'u',
  'h2',
  'h3',
  'ul',
  'ol',
  'li',
  'a',
  'blockquote',
];

const ALLOWED_ATTR = ['href', 'target', 'rel'];

export function stripHtml(value, maxLength = 0) {
  const text = String(value || '')
    .replace(/<[^>]*>/g, ' ')
    .replace(/&nbsp;/gi, ' ')
    .replace(/&amp;/gi, '&')
    .replace(/&lt;/gi, '<')
    .replace(/&gt;/gi, '>')
    .replace(/&quot;/gi, '"')
    .replace(/&#39;/gi, "'")
    .replace(/\s+/g, ' ')
    .trim();

  if (!maxLength || text.length <= maxLength) {
    return text;
  }

  return `${text.slice(0, maxLength).trimEnd()}…`;
}

export function isEmptyHtml(value) {
  if (!value) return true;
  return stripHtml(value).length === 0;
}

export function normalizeEditorHtml(value) {
  if (isEmptyHtml(value)) return '';
  return String(value).trim();
}

export function safeHtml(value) {
  if (isEmptyHtml(value)) return '';

  return DOMPurify.sanitize(String(value), {
    ALLOWED_TAGS,
    ALLOWED_ATTR,
    ALLOW_DATA_ATTR: false,
  });
}
