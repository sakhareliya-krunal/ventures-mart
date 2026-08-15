import api from '@/services/api';

let loading = false;
let loaded = false;

function pixelId() {
  return String(typeof window !== 'undefined' ? window.__APP__?.metaPixelId || '' : '').trim();
}

function cookieValue(name) {
  if (typeof document === 'undefined') return '';
  const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));
  return match ? decodeURIComponent(match[1]) : '';
}

function newEventId() {
  return globalThis.crypto?.randomUUID?.() || `meta-${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

export function initMetaPixel() {
  const id = pixelId();
  if (!id || typeof window === 'undefined' || loaded || loading) {
    return;
  }

  if (window.fbq) {
    loaded = true;
    return;
  }

  loading = true;

  const fbq = function fbqStub() {
    fbq.callMethod ? fbq.callMethod.apply(fbq, arguments) : fbq.queue.push(arguments);
  };
  fbq.push = fbq;
  fbq.loaded = true;
  fbq.version = '2.0';
  fbq.queue = [];
  window.fbq = fbq;
  window._fbq = fbq;

  fbq('set', 'autoConfig', false, id);
  fbq('init', id);

  const script = document.createElement('script');
  script.async = true;
  script.src = 'https://connect.facebook.net/en_US/fbevents.js';
  script.onload = () => {
    loaded = true;
    loading = false;
  };
  script.onerror = () => {
    loading = false;
  };
  document.head.appendChild(script);
}

/**
 * @param {string} eventName
 * @param {Record<string, unknown>} [params]
 * @param {{ eventId?: string, sendCapi?: boolean }} [options]
 */
export function trackMetaEvent(eventName, params = {}, options = {}) {
  const id = pixelId();
  if (!id) {
    return '';
  }

  initMetaPixel();

  const eventId = options.eventId || newEventId();
  const sendCapi = options.sendCapi !== false;

  try {
    window.fbq?.('track', eventName, params, { eventID: eventId });
  } catch {
    // Pixel must never break checkout.
  }

  if (sendCapi) {
    api
      .post('/meta/events', {
        event_name: eventName,
        event_id: eventId,
        event_source_url: typeof window !== 'undefined' ? window.location.href : '',
        custom_data: params,
        fbp: cookieValue('_fbp'),
        fbc: cookieValue('_fbc'),
      })
      .catch(() => {});
  }

  return eventId;
}

export function productMetaParams(product, quantity = 1) {
  if (!product?.id) {
    return {};
  }

  const qty = Math.max(1, Number(quantity) || 1);
  const price = Number(product.price || 0);

  return {
    content_ids: [String(product.id)],
    content_name: product.name,
    content_type: 'product',
    currency: 'INR',
    value: Math.round(price * qty * 100) / 100,
    contents: [{ id: String(product.id), quantity: qty, item_price: price }],
  };
}

export function cartMetaParams(items, total) {
  const lines = Array.isArray(items) ? items : [];
  const contents = [];
  const ids = [];
  const names = [];
  let quantity = 0;

  for (const item of lines) {
    const id = item.product_id || item.product?.id;
    if (!id) continue;
    const qty = Number(item.quantity || 1);
    ids.push(String(id));
    if (item.product?.name) names.push(item.product.name);
    quantity += qty;
    contents.push({
      id: String(id),
      quantity: qty,
      item_price: Number(item.product?.price || 0),
    });
  }

  return {
    content_ids: ids,
    content_name: names.join(', '),
    content_type: 'product',
    currency: 'INR',
    value: Number(total || 0),
    contents,
    num_items: quantity,
  };
}

export function orderMetaParams(order) {
  const items = Array.isArray(order?.items) ? order.items : [];
  const contents = [];
  const ids = [];
  const names = [];

  for (const item of items) {
    const id = item.product_id || item.sku;
    if (!id) continue;
    ids.push(String(id));
    if (item.name) names.push(item.name);
    contents.push({
      id: String(id),
      quantity: Number(item.quantity || 1),
      item_price: Number(item.unit_price || 0),
    });
  }

  return {
    content_ids: ids,
    content_name: names.join(', '),
    content_type: 'product',
    currency: 'INR',
    value: Number(order?.total || 0),
    contents,
    num_items: items.reduce((sum, item) => sum + Number(item.quantity || 0), 0),
  };
}
