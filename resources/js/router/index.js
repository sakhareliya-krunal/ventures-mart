import { createRouter, createWebHistory } from 'vue-router';
import MainLayout from '@/layouts/MainLayout.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';

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
          name: 'returns',
          component: () => import('@/pages/ReturnsPage.vue'),
        },
        {
          path: 'payments',
          name: 'payments',
          component: () => import('@/pages/PaymentsPage.vue'),
        },
        {
          path: 'blog',
          name: 'blog',
          component: () => import('@/pages/BlogIndexPage.vue'),
        },
        {
          path: 'blog/:slug',
          name: 'blog-post',
          component: () => import('@/pages/BlogPostPage.vue'),
        },
      ],
    },
    {
      path: '/admin',
      component: AdminLayout,
      meta: { requiresAuth: true, requiresAdmin: true },
      children: [
        {
          path: '',
          name: 'admin-dashboard',
          component: () => import('@/pages/admin/AdminDashboardPage.vue'),
          meta: { title: 'Dashboard' },
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
          component: () => import('@/pages/NotFoundPage.vue'),
        },
      ],
    },
  ],
});

router.beforeEach(async (to, from) => {
  const auth = useAuthStore();
  const ui = useUiStore();

  if (to.fullPath !== from.fullPath) {
    ui.startNavigating();
  }

  const needsAuth = to.matched.some((record) => record.meta.requiresAuth);
  const needsAdmin = to.matched.some((record) => record.meta.requiresAdmin);
  const isAdminRoute = to.path === '/admin' || to.path.startsWith('/admin/');
  const isAuthPage =
    to.name === 'login' ||
    to.name === 'register' ||
    to.name === 'forgot-password' ||
    to.name === 'reset-password';

  if (!auth.user && !auth.booting) {
    await auth.fetchUser();
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

router.afterEach(() => {
  const auth = useAuthStore();
  const ui = useUiStore();

  ui.stopNavigating();

  // Always clear the redirect overlay after a confirmed navigation so a
  // duplicate/same-route follow-up cannot leave redirecting stuck true.
  if (auth.redirecting) {
    auth.endRedirect();
  }
});

router.onError(() => {
  useUiStore().stopNavigating();
});

export default router;
