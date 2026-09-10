const ADMIN_STOREFRONT_ROUTES = new Set(['contact']);
const AUTH_ROUTES = new Set(['login', 'register', 'forgot-password', 'reset-password']);

export function shouldRedirectAdminToPanel(route, isAdmin) {
  if (!isAdmin) return false;

  const path = String(route?.path || '/');
  if (path === '/admin' || path.startsWith('/admin/')) return false;
  if (AUTH_ROUTES.has(route?.name)) return false;

  return !ADMIN_STOREFRONT_ROUTES.has(route?.name);
}
