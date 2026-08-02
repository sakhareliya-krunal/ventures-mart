export function formatCurrency(value) {
  return new Intl.NumberFormat('en-IN', {
    style: 'currency',
    currency: 'INR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(Number(value) || 0);
}

/** Whole-number % off when compare-at is higher than price; otherwise null. */
export function discountPercent(price, compareAt) {
  const current = Number(price);
  const was = Number(compareAt);
  if (!Number.isFinite(current) || !Number.isFinite(was) || was <= current || current < 0) {
    return null;
  }
  return Math.round(((was - current) / was) * 100);
}

export function unwrapData(payload) {
  if (payload == null) {
    return payload;
  }

  if (Array.isArray(payload)) {
    return payload;
  }

  if (Object.prototype.hasOwnProperty.call(payload, 'data')) {
    return payload.data;
  }

  return payload;
}
