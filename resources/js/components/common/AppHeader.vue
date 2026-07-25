<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { Heart, Menu, ShoppingBag, User } from '@lucide/vue';
import { RouterLink } from 'vue-router';
import CartBadge from '@/components/common/CartBadge.vue';
import MobileDrawer from '@/components/common/MobileDrawer.vue';
import { primaryNav } from '@/constants/navigation';
import { brandAssets } from '@/constants/assets';
import { useAuthStore } from '@/stores/auth';
import { useCartStore } from '@/stores/cart';
import { useWishlistStore } from '@/stores/wishlist';
import { useThemeStore } from '@/stores/theme';

const open = ref(false);
const router = useRouter();
const auth = useAuthStore();
const cart = useCartStore();
const wishlist = useWishlistStore();
const theme = useThemeStore();

const profileTo = computed(() => {
  if (!auth.user) {
    return '/login';
  }

  return auth.isAdmin ? '/admin' : '/profile';
});

function openCart() {
  if (cart.isEmpty) {
    router.push('/shop');
    return;
  }

  cart.openTray();
}
</script>

<template>
  <header class="site-header">
    <div class="top-strip">
      Free shipping on orders over ₹999. Carefully curated for kids and families.
    </div>
    <div class="header-main">
      <RouterLink class="brand" to="/" :aria-label="`${theme.brandName} home`">
        <img :src="brandAssets.logo" :alt="theme.brandName" />
      </RouterLink>

      <nav class="desktop-nav" aria-label="Primary navigation">
        <RouterLink
          v-for="item in primaryNav"
          :key="item.href"
          :to="item.href"
          :active-class="item.href === '/' ? '' : 'active'"
          exact-active-class="active"
        >
          {{ item.label }}
        </RouterLink>
      </nav>

      <div class="header-actions">
        <RouterLink
          v-if="auth.isAdmin"
          class="header-admin-link"
          to="/admin"
        >
          Admin
        </RouterLink>
        <RouterLink class="icon-link" :to="profileTo" :aria-label="auth.user ? 'Profile' : 'Login'">
          <User :size="20" />
        </RouterLink>
        <RouterLink class="icon-link" to="/wishlist" aria-label="Wishlist">
          <Heart :size="20" />
          <CartBadge :count="wishlist.count" />
        </RouterLink>
        <button class="icon-link" type="button" aria-label="Cart" @click="openCart">
          <ShoppingBag :size="20" />
          <CartBadge :count="cart.itemCount" />
        </button>
        <button
          class="icon-button mobile-menu-button"
          type="button"
          aria-label="Open menu"
          :aria-expanded="open"
          @click="open = true"
        >
          <Menu :size="22" />
        </button>
      </div>
    </div>

    <MobileDrawer :open="open" @close="open = false" />
  </header>
</template>
