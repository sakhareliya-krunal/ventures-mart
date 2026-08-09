/**
 * Turn API/network failures into safe, customer-facing copy.
 * Never surfaces SQL, stack traces, or exception class names.
 *
 * @param {unknown} err
 * @param {string} [fallback]
 * @returns {string}
 */
export function friendlyApiError(err, fallback = 'Something went wrong. Please try again.') {
  if (!err) return fallback;

  if (!err.response) {
    // Network/timeout/offline: global loader + retries handle UX; no toast copy.
    return '';
  }

  const status = err.response.status;
  const data = err.response.data || {};

  if (status === 422) {
    const firstField = Object.values(data.errors || {})[0];
    if (Array.isArray(firstField) && firstField[0]) {
      return String(firstField[0]);
    }
    if (typeof data.message === 'string' && data.message && !looksTechnical(data.message)) {
      return data.message;
    }
    return 'Please check the highlighted fields and try again.';
  }

  if (data.code === 'payment_init_failed') {
    if (typeof data.message === 'string' && data.message && !looksTechnical(data.message)) {
      return data.message;
    }
    return 'Unable to start payment. Please try again.';
  }

  if (data.code === 'order_create_failed') {
    return 'Unable to place your order. Please try again.';
  }

  if (data.code === 'error_logs_unavailable') {
    if (typeof data.message === 'string' && data.message && !looksTechnical(data.message)) {
      return data.message;
    }
    return 'Error logs are not ready. Run database migrations.';
  }

  const mapped = {
    401: 'Please sign in to continue.',
    403: "You don't have permission to do that.",
    404: 'The requested item could not be found.',
    419: 'Your session expired. Please refresh and try again.',
    429: 'Too many requests. Please wait a moment and try again.',
    503: 'The service is temporarily unavailable. Please try again soon.',
  };

  if (mapped[status]) {
    return mapped[status];
  }

  if (status >= 500) {
    return 'Something went wrong. Please try again.';
  }

  if (typeof data.message === 'string' && data.message && !looksTechnical(data.message)) {
    return data.message;
  }

  return fallback;
}

/**
 * True for offline, DNS, CORS-blocked, and axios timeout failures (no HTTP response).
 *
 * @param {unknown} err
 * @returns {boolean}
 */
export function isNetworkOrTimeoutError(err) {
  if (!err || err.response) {
    return false;
  }

  // No HTTP response: timeout, offline, DNS, aborted request, etc.
  return true;
}

/**
 * @param {string} message
 * @returns {boolean}
 */
export function looksTechnical(message) {
  const text = String(message || '');
  return (
    /SQLSTATE|Integrity constraint|Stack trace|Illuminate\\|Symfony\\|at \/|#[0-9]+ |Exception|ErrorException|PDOException|Undefined (variable|index|array key)|Call to undefined|Whoops!/i.test(
      text,
    ) || text.length > 280
  );
}
