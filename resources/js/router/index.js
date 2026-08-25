import { createRouter, createWebHistory, START_LOCATION } from 'vue-router';
import { watch } from 'vue';
import MainLayout from '@/layouts/MainLayout.vue';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import { syncSeoForPath } from '@/utils/seoHead';
import { trackMetaEvent } from '@/services/metaPixel';

const router = createRouter({
  history: createWebHistory(),
  scrollBehavior() {
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    return { top: 0, behavior: reduced ? 'auto' : 'smooth' };
  },
  routes: [
    {
      path: '/',
      component: MainLayout,
      children: [
        {
          path: '',
          name: 'home',
          component: () => import('@/pages/HomePage.vue'),
        },
        {
          path: 'shop',
          name: 'shop',
          component: () => import('@/pages/ShopPage.vue'),
        },
        {
          path: 'category/:slug',
          name: 'category',
          component: () => import('@/pages/CategoryPage.vue'),
        },
        {
          path: 'product/:slug',
          name: 'product',
          component: () => import('@/pages/ProductPage.vue'),
        },
        {
          path: 'search',
          name: 'search',
          component: () => import('@/pages/SearchPage.vue'),
        },
        {
          path: 'wishlist',
          name: 'wishlist',
          component: () => import('@/pages/WishlistPage.vue'),
          meta: { requiresAuth: true },
        },
        {
          path: 'cart',
          name: 'cart',
          component: () => import('@/pages/CartPage.vue'),
        },
        {
          path: 'checkout',
          name: 'checkout',
          component: () => import('@/pages/CheckoutPage.vue'),
          meta: { requiresAuth: true },
        },
        {
          path: 'login',
          name: 'login',
          component: () => import('@/pages/LoginPage.vue'),
        },
        {
          path: 'register',
          name: 'register',
          component: () => import('@/pages/RegisterPage.vue'),
        },
        {
          path: 'forgot-password',
          name: 'forgot-password',
          component: () => import('@/pages/ForgotPasswordPage.vue'),
        },
        {
          path: 'reset-password',
          name: 'reset-password',
          component: () => import('@/pages/ResetPasswordPage.vue'),
        },
        {
          path: 'profile',
          name: 'profile',
          component: () => import('@/pages/ProfilePage.vue'),
          meta: { requiresAuth: true },
        },
        {
          path: 'orders',
          name: 'orders',
          component: () => import('@/pages/OrdersPage.vue'),
          meta: { requiresAuth: true },
        },
        {
          path: 'orders/:id/confirmed',
          name: 'order-confirmed',
          component: () => import('@/pages/OrderConfirmationPage.vue'),
          meta: { requiresAuth: true },
        },
        {
          path: 'orders/:number',
          name: 'order-track',
          component: () => import('@/pages/OrderTrackPage.vue'),
          meta: { requiresAuth: true },
        },
        {
          path: 'contact',
          name: 'contact',
          component: () => import('@/pages/ContactPage.vue'),
        },
        {
          path: 'about',
          name: 'about',
          component: () => import('@/pages/AboutPage.vue'),
        },
        {
          path: 'privacy-policy',
          name: 'privacy',
          component: () => import('@/pages/PrivacyPage.vue'),
        },
        {
          path: 'terms',
          name: 'terms',
          component: () => import('@/pages/TermsPage.vue'),
        },
        {
          path: 'shipping',
          name: 'shipping',
          component: () => import('@/pages/ShippingPage.vue'),
        },
        {
          path: 'returns',
          redirect: { name: 'replacement' },
        },
        {
          path: 'replacement',
          name: 'replacement',
          component: () => import('@/pages/ReplacementPage.vue'),
        },
        {
          path: 'payments',
          name: 'payments',
          component: () => import('@/pages/PaymentsPage.vue'),
        },
        {
          path: 'shopping-confidence-shipping-replacement',
          name: 'shopping-confidence',
          component: () => import('@/pages/ShoppingConfidencePage.vue'),
        },
        {
          path: 'blog',
          name: 'blog',
          component: () => import('@/pages/BlogIndexPage.vue'),
        },
        {
          path: 'blog/shopping-confidence-shipping-replacement',
          redirect: { name: 'shopping-confidence' },
        },
        {
          path: 'errors/:code',
          name: 'error-status',
          component: () => import('@/pages/ErrorStatusPage.vue'),
        },
        {
          path: 'blog/:slug',
          name: 'blog-post',
          component: () => import('@/pages/BlogPostPage.vue'),
        },
      ],
    },
    {
      path: '/error',
      redirect: '/admin/error',
    },
    {
      path: '/admin/errors',
      redirect: '/admin/error',
    },
    {
      path: '/admin',
      component: () => import('@/layouts/AdminLayout.vue'),
      meta: { requiresAuth: true, requiresAdmin: true },
      children: [
        {
          path: '',
          name: 'admin-dashboard',
          component: () => import('@/pages/admin/AdminDashboardPage.vue'),
          meta: { title: 'Dashboard' },
        },
        {
          path: 'error',
          name: 'admin-errors',
          component: () => import('@/pages/admin/AdminErrorsPage.vue'),
          meta: { title: 'Error logs' },
        },
        {
          path: 'orders',
          name: 'admin-orders',
          component: () => import('@/pages/admin/AdminOrdersPage.vue'),
          meta: { title: 'Orders' },
        },
        {
          path: 'orders/:id',
          name: 'admin-order-detail',
          component: () => import('@/pages/admin/AdminOrderDetailPage.vue'),
          meta: { title: 'Order detail' },
        },
        {
          path: 'replacements',
          name: 'admin-replacements',
          component: () => import('@/pages/admin/AdminReplacementsPage.vue'),
          meta: { title: 'Replacements' },
        },
        {
          path: 'products/create',
          name: 'admin-product-create',
          component: () => import('@/pages/admin/AdminProductCreatePage.vue'),
          meta: { title: 'New product' },
        },
        {
          path: 'products/:id/edit',
          name: 'admin-product-edit',
          component: () => import('@/pages/admin/AdminProductEditPage.vue'),
          meta: { title: 'Edit product' },
        },
        {
          path: 'products',
          name: 'admin-products',
          component: () => import('@/pages/admin/AdminProductsPage.vue'),
          meta: { title: 'Products' },
        },
        {
          path: 'inventory',
          name: 'admin-inventory',
          component: () => import('@/pages/admin/AdminInventoryPage.vue'),
          meta: { title: 'Inventory' },
        },
        {
          path: 'categories',
          name: 'admin-categories',
          component: () => import('@/pages/admin/AdminCategoriesPage.vue'),
          meta: { title: 'Categories' },
        },
        {
          path: 'posts/create',
          name: 'admin-post-create',
          component: () => import('@/pages/admin/AdminPostCreatePage.vue'),
          meta: { title: 'New post' },
        },
        {
          path: 'posts/:id/edit',
          name: 'admin-post-edit',
          component: () => import('@/pages/admin/AdminPostEditPage.vue'),
          meta: { title: 'Edit post' },
        },
        {
          path: 'posts',
          name: 'admin-posts',
          component: () => import('@/pages/admin/AdminPostsPage.vue'),
          meta: { title: 'Blog posts' },
        },
        {
          path: 'contacts',
          name: 'admin-contacts',
          component: () => import('@/pages/admin/AdminContactsPage.vue'),
          meta: { title: 'Contact messages' },
        },
        {
          path: 'customers',
          name: 'admin-customers',
          component: () => import('@/pages/admin/AdminUsersPage.vue'),
          meta: { title: 'Customers' },
        },
        {
          path: 'addresses',
          name: 'admin-addresses',
          component: () => import('@/pages/admin/AdminAddressesPage.vue'),
          meta: { title: 'Addresses' },
        },
        {
          path: 'account/create-admin',
          name: 'admin-create-admin',
          component: () => import('@/pages/admin/AdminUserCreatePage.vue'),
          meta: { title: 'Create admin' },
        },
        {
          path: 'account',
          name: 'admin-account',
          component: () => import('@/pages/admin/AdminAccountPage.vue'),
          meta: { title: 'Profile' },
        },
        {
          path: 'settings',
          name: 'admin-settings',
          component: () => import('@/pages/admin/AdminSettingsPage.vue'),
          meta: { title: 'Settings' },
        },
      ],
    },
    {
      path: '/:pathMatch(.*)*',
      component: MainLayout,
      children: [
        {
          path: '',
          name: 'not-found',
          component: () => import('@/pages/ErrorStatusPage.vue'),
          props: { code: 404 },
          meta: { errorCode: 404 },
        },
      ],
    },
  ],
});

let adminRedirectWatcherReady = false;

// Safety net: if admin status resolves after the initial navigation (slow
// /user response, or a login in another tab), correct a stale storefront view
// by sending the admin back to the admin panel. Registered lazily on the first
// guard run so Pinia is guaranteed to be active.
function ensureAdminRedirectWatcher(auth) {
  if (adminRedirectWatcherReady) {
    return;
  }
  adminRedirectWatcherReady = true;

  watch(
    () => auth.isAdmin,
    (isAdmin) => {
      if (!isAdmin) {
        return;
      }

      const current = router.currentRoute.value;
      const path = current.path || '/';
      const onAuthPage = ['login', 'register', 'forgot-password', 'reset-password'].includes(
        current.name,
      );

      if (!path.startsWith('/admin') && !onAuthPage) {
        router.replace('/admin');
      }
    },
  );
}

function routePathWithoutHash(route) {
  return String(route.fullPath || '').split('#')[0];
}

router.beforeEach(async (to, from) => {
  const auth = useAuthStore();
  const ui = useUiStore();

  ensureAdminRedirectWatcher(auth);

  const routeChanged = from !== START_LOCATION && routePathWithoutHash(to) !== routePathWithoutHash(from);

  if (routeChanged) {
    ui.startNavigating();
  }

  const needsAuth = to.matched.some((record) => record.meta.requiresAuth);
  const needsAdmin = to.matched.some((record) => record.meta.requiresAdmin);
  const isAdminRoute =
    to.path === '/admin' ||
    to.path.startsWith('/admin/');

  const isAuthPage =
    to.name === 'login' ||
    to.name === 'register' ||
    to.name === 'forgot-password' ||
    to.name === 'reset-password';

  if (!auth.user) {
    const session = auth.fetchUser();
    const needsSession =
      needsAuth || needsAdmin || isAdminRoute || isAuthPage || from === START_LOCATION;

    if (needsSession) {
      await session;
    }
  }

  if (needsAuth || needsAdmin) {
    if (!auth.user) {
      auth.beginRedirect();
      return {
        name: 'login',
        query: { redirect: to.fullPath },
      };
    }
  }

  if (needsAdmin && !auth.isAdmin) {
    return { name: 'home' };
  }

  if (to.name === 'profile' && auth.isAdmin) {
    return { name: 'admin-account' };
  }

  if (auth.isAdmin && !isAdminRoute && !isAuthPage) {
    return { path: '/admin' };
  }

  return true;
});

router.afterEach((to, from, failure) => {
  const auth = useAuthStore();
  const ui = useUiStore();

  ui.stopNavigating();

  // Always clear route transition state after success, redirect, cancellation,
  // or duplicated navigation so the lightweight loader cannot remain active.
  if (auth.redirecting || failure) {
    auth.endRedirect();
  }

  if (!String(to.path || '').startsWith('/admin')) {
    syncSeoForPath(to.path || '/');
    trackMetaEvent('PageView');
  }
});

router.onError(() => {
  useUiStore().stopNavigating();
  useAuthStore().endRedirect();
});

export default router;
