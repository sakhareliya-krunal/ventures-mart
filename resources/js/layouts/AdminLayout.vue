<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router';
import {
  LayoutDashboard,
  Package,
  ShoppingBag,
  Tags,
  FileText,
  Mail,
  Users,
  MapPin,
  User,
  Settings,
  LogOut,
  ChevronUp,
  Menu,
  X,
} from '@lucide/vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import { brandAssets } from '@/constants/assets';
import { useAuthStore } from '@/stores/auth';
import { useThemeStore } from '@/stores/theme';

const auth = useAuthStore();
const theme = useThemeStore();
const route = useRoute();
const router = useRouter();
const confirmLogoutOpen = ref(false);
const navOpen = ref(false);
const accountMenuOpen = ref(false);

const nav = [
  { to: '/admin', label: 'Dashboard', icon: LayoutDashboard, exact: true },
  { to: '/admin/orders', label: 'Orders', icon: ShoppingBag },
  { to: '/admin/products', label: 'Products', icon: Package },
  { to: '/admin/categories', label: 'Categories', icon: Tags },
  { to: '/admin/posts', label: 'Blog posts', icon: FileText },
  { to: '/admin/contacts', label: 'Contact messages', icon: Mail },
  { to: '/admin/customers', label: 'Customers', icon: Users },
  { to: '/admin/addresses', label: 'Addresses', icon: MapPin },
];

const pageTitle = computed(() => route.meta.title || 'Admin');

const accountMenuActive = computed(
  () =>
    route.path === '/admin/account' ||
    route.path.startsWith('/admin/account/') ||
    route.path === '/admin/settings',
);

function isActive(item) {
  if (item.exact) {
    return route.path === item.to;
  }
  return route.path === item.to || route.path.startsWith(`${item.to}/`);
}

function closeNav() {
  navOpen.value = false;
}

function closeAccountMenu() {
  accountMenuOpen.value = false;
}

function toggleAccountMenu() {
  accountMenuOpen.value = !accountMenuOpen.value;
}

function toggleNav() {
  navOpen.value = !navOpen.value;
}

function onKeydown(event) {
  if (event.key === 'Escape') {
    closeAccountMenu();
    closeNav();
  }
}

function lockScroll(locked) {
  document.body.style.overflow = locked ? 'hidden' : '';
}

function requestLogout() {
  closeAccountMenu();
  closeNav();
  confirmLogoutOpen.value = true;
}

async function logout() {
  await auth.logout();
  confirmLogoutOpen.value = false;
  await router.push('/login');
}

watch(
  () => route.fullPath,
  () => {
    closeNav();
    closeAccountMenu();
  },
);

watch(navOpen, (isOpen) => {
  lockScroll(isOpen);
});

watch([navOpen, accountMenuOpen], ([isNavOpen, isAccountOpen]) => {
  if (isNavOpen || isAccountOpen) {
    window.addEventListener('keydown', onKeydown);
  } else {
    window.removeEventListener('keydown', onKeydown);
  }
});

function onViewportChange(event) {
  if (event.matches) {
    closeNav();
  }
}

const desktopQuery = window.matchMedia('(min-width: 961px)');
desktopQuery.addEventListener('change', onViewportChange);

onBeforeUnmount(() => {
  lockScroll(false);
  window.removeEventListener('keydown', onKeydown);
  desktopQuery.removeEventListener('change', onViewportChange);
});
</script>

<template>
  <div class="admin-shell" :class="{ 'admin-shell--nav-open': navOpen }">
    <button
      v-if="navOpen"
      class="admin-sidebar__backdrop"
      type="button"
      aria-label="Close menu"
      @click="closeNav"
    />

    <aside class="admin-sidebar" :class="{ 'is-open': navOpen }" id="admin-sidebar">
      <div class="admin-sidebar__brand">
        <div class="admin-sidebar__brand-row">
          <img :src="brandAssets.logoLight" :alt="theme.brandName" />
          <button
            class="admin-sidebar__close"
            type="button"
            aria-label="Close menu"
            @click="closeNav"
          >
            <X :size="20" />
          </button>
        </div>
        <span>Admin</span>
      </div>

      <nav class="admin-sidebar__nav" aria-label="Admin">
        <RouterLink
          v-for="item in nav"
          :key="item.to"
          :to="item.to"
          class="admin-nav-link"
          :class="{ 'is-active': isActive(item) }"
          @click="closeNav"
        >
          <component :is="item.icon" :size="18" />
          <span>{{ item.label }}</span>
        </RouterLink>
      </nav>

      <div class="admin-sidebar__account" :class="{ 'is-open': accountMenuOpen }">
        <div
          v-if="accountMenuOpen"
          id="admin-account-menu"
          class="admin-sidebar__account-menu"
          role="menu"
        >
          <RouterLink
            to="/admin/account"
            class="admin-nav-link"
            :class="{ 'is-active': route.path === '/admin/account' || route.path.startsWith('/admin/account/') }"
            role="menuitem"
            @click="closeNav"
          >
            <User :size="18" />
            <span>Profile</span>
          </RouterLink>
          <RouterLink
            to="/admin/settings"
            class="admin-nav-link"
            :class="{ 'is-active': route.path === '/admin/settings' }"
            role="menuitem"
            @click="closeNav"
          >
            <Settings :size="18" />
            <span>Settings</span>
          </RouterLink>
          <button
            type="button"
            class="admin-nav-link admin-nav-link--button"
            role="menuitem"
            :disabled="auth.loggingOut"
            @click="requestLogout"
          >
            <LogOut :size="18" />
            <span>Logout</span>
          </button>
        </div>

        <button
          type="button"
          class="admin-sidebar__account-toggle"
          :class="{ 'is-active': accountMenuActive }"
          :aria-expanded="accountMenuOpen"
          aria-controls="admin-account-menu"
          @click="toggleAccountMenu"
        >
          <User :size="18" />
          <span class="admin-sidebar__account-label">
            <span class="admin-sidebar__account-title">My account</span>
            <span class="admin-sidebar__account-name">{{ auth.user?.name || 'Admin' }}</span>
          </span>
          <ChevronUp :size="16" class="admin-sidebar__account-chevron" />
        </button>
      </div>
    </aside>

    <div class="admin-main">
      <header class="admin-topbar">
        <div class="admin-topbar__start">
          <button
            class="admin-topbar__menu"
            type="button"
            :aria-expanded="navOpen"
            aria-controls="admin-sidebar"
            aria-label="Open menu"
            @click="toggleNav"
          >
            <Menu :size="20" />
          </button>
          <div class="admin-topbar__title">
            <p class="admin-topbar__eyebrow">Administration</p>
            <h1>{{ pageTitle }}</h1>
          </div>
        </div>
        <div class="admin-topbar__actions">
          <span class="admin-topbar__user">{{ auth.user?.name }}</span>
        </div>
      </header>
      <main class="admin-content">
        <RouterView />
      </main>
    </div>

    <ConfirmDialog
      v-model:open="confirmLogoutOpen"
      title="Log out?"
      message="You will leave the admin panel and need to sign in again."
      confirm-label="Log out"
      busy-label="Signing out…"
      :busy="auth.loggingOut"
      :close-on-confirm="false"
      danger
      @confirm="logout"
    />
  </div>
</template>
