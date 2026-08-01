import axios from 'axios';
import { friendlyApiError, isNetworkOrTimeoutError } from '@/utils/apiError';

const ADMIN_NETWORK_MAX_RETRIES = 20;
const ADMIN_NETWORK_RETRY_DELAY_MS = 1500;

const api = axios.create({
  baseURL: '/api',
  timeout: 8000,
  withCredentials: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
});

function readCookie(name) {
  const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));
  return match ? decodeURIComponent(match[1]) : null;
}

function readCsrfToken() {
  return (
    readCookie('XSRF-TOKEN') ||
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
    null
  );
}

let csrfPromise = null;

export async function ensureCsrfCookie({ force = false } = {}) {
  if (!force && readCsrfToken()) {
    return;
  }

  if (!csrfPromise) {
    csrfPromise = axios
      .get('/sanctum/csrf-cookie', { withCredentials: true })
      .finally(() => {
        csrfPromise = null;
      });
  }

  await csrfPromise;
}

function toastError(message) {
  import('@/stores/ui')
    .then(({ useUiStore }) => {
      useUiStore().showToast(message, { type: 'error', durationMs: 4500 });
    })
    .catch(() => {
      // Pinia may not be ready during early boot.
    });
}

function shouldSkipGlobalToast(config = {}) {
  return Boolean(config.skipErrorToast);
}

function isQuietUnauthRequest(config = {}) {
  const url = String(config.url || '');
  return url.includes('/user') && (config.method || 'get').toLowerCase() === 'get';
}

function isAdminRequest(config = {}) {
  return String(config.url || '').includes('/admin');
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

api.interceptors.request.use(async (config) => {
  const method = (config.method || 'get').toLowerCase();
  const isMutating = !['get', 'head', 'options'].includes(method);

  if (isMutating) {
    await ensureCsrfCookie();
  }

  const token = readCsrfToken();
  if (token) {
    config.headers['X-XSRF-TOKEN'] = token;
    config.headers['X-CSRF-TOKEN'] = token;
  }

  return config;
});

api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const config = error.config || {};
    const status = error.response?.status;

    if (status === 419 && !config.__csrfRetried) {
      config.__csrfRetried = true;
      try {
        await ensureCsrfCookie({ force: true });
        const token = readCsrfToken();
        if (token) {
          config.headers = config.headers || {};
          config.headers['X-XSRF-TOKEN'] = token;
          config.headers['X-CSRF-TOKEN'] = token;
        }
        return api.request(config);
      } catch {
        // Fall through to friendly handling.
      }
    }

    if (isNetworkOrTimeoutError(error) && isAdminRequest(config)) {
      const method = (config.method || 'get').toLowerCase();
      if (method === 'get') {
        const attempt = config.__adminNetworkRetries || 0;
        if (attempt < ADMIN_NETWORK_MAX_RETRIES) {
          config.__adminNetworkRetries = attempt + 1;
          await sleep(ADMIN_NETWORK_RETRY_DELAY_MS);
          return api.request(config);
        }
      }

      return Promise.reject(error);
    }

    if (!shouldSkipGlobalToast(config)) {
      if (!error.response) {
        toastError(friendlyApiError(error));
      } else if (status === 419) {
        toastError(friendlyApiError(error));
      } else if (status === 429) {
        toastError(friendlyApiError(error));
      } else if (status === 403) {
        toastError(friendlyApiError(error));
      } else if (status === 401 && !isQuietUnauthRequest(config)) {
        toastError(friendlyApiError(error));
      } else if (status >= 500) {
        toastError(friendlyApiError(error));
      }
    }

    return Promise.reject(error);
  },
);

export default api;
