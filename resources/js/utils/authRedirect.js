import { useAuthStore } from '@/stores/auth';

/**
 * After login/register, honor a return path unless it is empty or home.
 * Home (`/`) uses the given fallback (storefront home for customers).
 * @param {{ pending?: unknown, redirect?: unknown, fallback: string }} options
 */
export function resolvePostAuthPath({ pending, redirect, fallback }) {
  const candidate = String(pending || redirect || fallback || '').trim();

  if (!candidate || candidate === '/') {
    return fallback;
  }

  return candidate;
}

/**
 * Send guests to login, then return them to the intended path after auth.
 * @param {import('vue-router').Router} router
 * @param {string} [redirectPath]
 */
export function requireLogin(router, redirectPath) {
  const auth = useAuthStore();
  const redirect = redirectPath || router.currentRoute.value.fullPath || '/';

  auth.beginRedirect();

  return router.push({
    name: 'login',
    query: { redirect },
  }).catch(() => {
    auth.endRedirect();
  });
}
