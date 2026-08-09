const ORDER_STATUS_LABELS = {
  AwaitingPayment: 'Awaiting payment',
  InventoryHold: 'Inventory hold',
  Processing: 'Confirmed',
  Packed: 'Packed',
  Shipped: 'Shipped',
  Delivered: 'Delivered',
  Cancelled: 'Cancelled',
};

const ORDER_STATUS_CLASSES = {
  AwaitingPayment: 'admin-badge--warn',
  InventoryHold: 'admin-badge--danger',
  Processing: 'admin-badge--info',
  Packed: 'admin-badge--info',
  Shipped: 'admin-badge--info',
  Delivered: 'admin-badge--ok',
  Cancelled: 'admin-badge--danger',
};

const PAYMENT_STATUS_LABELS = {
  pending: 'Pending',
  paid: 'Paid',
  failed: 'Failed',
  refunded: 'Refunded',
};

const PAYMENT_STATUS_CLASSES = {
  pending: 'admin-badge--warn',
  paid: 'admin-badge--ok',
  failed: 'admin-badge--danger',
  refunded: 'admin-badge--info',
};

function titleCase(value) {
  const text = String(value || '').trim();
  if (!text) return '—';
  return text.charAt(0).toUpperCase() + text.slice(1).toLowerCase();
}

export function orderStatusLabel(status) {
  if (!status) return '—';
  return ORDER_STATUS_LABELS[status] || String(status);
}

export function orderStatusBadgeClass(status) {
  return ORDER_STATUS_CLASSES[status] || '';
}

export function paymentStatusLabel(status) {
  if (!status) return '—';
  const key = String(status).toLowerCase();
  return PAYMENT_STATUS_LABELS[key] || titleCase(status);
}

export function paymentStatusBadgeClass(status) {
  if (!status) return 'admin-badge--warn';
  const key = String(status).toLowerCase();
  return PAYMENT_STATUS_CLASSES[key] || 'admin-badge--warn';
}
