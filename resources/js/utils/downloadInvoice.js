import api from '@/services/api';

/**
 * Download a tax invoice PDF for an order (customer or admin).
 * @param {number|string} orderId
 * @param {{ admin?: boolean }} [options]
 */
export async function downloadOrderInvoice(orderId, { admin = false } = {}) {
  const path = admin
    ? `/admin/orders/${orderId}/invoice`
    : `/orders/${orderId}/invoice`;

  return downloadInvoiceBlob(path, `Invoice-order-${orderId}.pdf`);
}

/**
 * Authenticated invoice download by order number (QR / track page).
 * @param {string} orderNumber
 */
export async function downloadTrackedOrderInvoice(orderNumber) {
  const path = `/orders/track/${encodeURIComponent(orderNumber)}/invoice`;
  return downloadInvoiceBlob(path, `Invoice-${orderNumber}.pdf`);
}

async function downloadInvoiceBlob(path, fallbackName) {
  const { data, headers } = await api.get(path, {
    responseType: 'blob',
  });

  const disposition = headers?.['content-disposition'] || headers?.['Content-Disposition'] || '';
  const match = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/.exec(disposition);
  const filename = match
    ? match[1].replace(/['"]/g, '').trim()
    : fallbackName;

  const blob = data instanceof Blob ? data : new Blob([data], { type: 'application/pdf' });
  const url = window.URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.URL.revokeObjectURL(url);
}
