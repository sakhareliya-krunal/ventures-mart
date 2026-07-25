import axios from 'axios';

const api = axios.create({
  baseURL: '/api',
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

export async function ensureCsrfCookie() {
  if (!csrfPromise) {
    csrfPromise = axios
      .get('/sanctum/csrf-cookie', { withCredentials: true })
      .finally(() => {
        csrfPromise = null;
      });
  }

  await csrfPromise;
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

export default api;
