import { useAuthStore } from '@/stores/auth';

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
