export function normalizeEmail(value) {
  const raw = String(value ?? '').trim();
  if (!raw) {
    return '';
  }

  const cut = raw.split(/LOCAL_ADMIN|PASSWORD=|\r|\n/)[0].trim();
  const match = cut.match(/^[^\s@]+@[^\s@]+\.[^\s@]+/);

  return match ? match[0] : cut;
}

export function emailHref(value) {
  const email = normalizeEmail(value);

  return email ? `mailto:${email}` : '';
}

export function phoneHref(value) {
  const phone = String(value ?? '').trim();
  const digits = phone.replace(/\D/g, '');

  if (!digits) {
    return '';
  }

  return `tel:${phone.startsWith('+') ? '+' : ''}${digits}`;
}
