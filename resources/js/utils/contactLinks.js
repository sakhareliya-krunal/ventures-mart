export function emailHref(value) {
  const email = String(value ?? '').trim();

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
