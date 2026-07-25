<script setup>
import { computed, ref } from 'vue';
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
  LogOut,
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

const nav = [
  { to: '/admin', label: 'Dashboard', icon: LayoutDashboard, exact: true },
  { to: '/admin/orders', label: 'Orders', icon: ShoppingBag },
  { to: '/admin/products', label: 'Products', icon: Package },
  { to: '/admin/categories', label: 'Categories', icon: Tags },
  { to: '/admin/posts', label: 'Blog posts', icon: FileText },
  { to: '/admin/contacts', label: 'Contact messages', icon: Mail },
  { to: '/admin/customers', label: 'Customers', icon: Users },
  { to: '/admin/addresses', label: 'Addresses', icon: MapPin },
  { to: '/admin/account', label: 'My account', icon: User },
];

const pageTitle = computed(() => route.meta.title || 'Admin');

function isActive(item) {
  if (item.exact) {
    return route.path === item.to;
  }
  return route.path === item.to || route.path.startsWith(`${item.to}/`);
}

function requestLogout() {
  confirmLogoutOpen.value = true;
}

async function logout() {
  await auth.logout();
  await router.push('/login');
}
</script>

<template>
  <div class="admin-shell">
    <aside class="admin-sidebar">
      <div class="admin-sidebar__brand">
        <img :src="brandAssets.logoLight" :alt="theme.brandName" />
        <span>Admin</span>
      </div>
      <nav class="admin-sidebar__nav" aria-label="Admin">
        <RouterLink
          v-for="item in nav"
          :key="item.to"
          :to="item.to"
          class="admin-nav-link"
          :class="{ 'is-active': isActive(item) }"
        >
          <component :is="item.icon" :size="18" />
          <span>{{ item.label }}</span>
        </RouterLink>
      </nav>
    </aside>

    <div class="admin-main">
      <header class="admin-topbar">
        <div class="admin-topbar__title">
          <p class="admin-topbar__eyebrow">Administration</p>
          <h1>{{ pageTitle }}</h1>
        </div>
        <div class="admin-topbar__actions">
          <span class="admin-topbar__user">{{ auth.user?.name }}</span>
          <button class="button button--ghost button--sm" type="button" @click="requestLogout">
            <LogOut :size="16" />
            Logout
          </button>
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
      danger
      @confirm="logout"
    />
  </div>
</template>
