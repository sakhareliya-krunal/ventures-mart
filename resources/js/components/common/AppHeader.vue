<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ChevronDown, Heart, Menu, ShoppingBag, User } from '@lucide/vue';
import { RouterLink } from 'vue-router';
import CartBadge from '@/components/common/CartBadge.vue';
import MobileDrawer from '@/components/common/MobileDrawer.vue';
import { primaryNav, shopNavChildren } from '@/constants/navigation';
import { brandAssets } from '@/constants/assets';
import { useAuthStore } from '@/stores/auth';
import { useCartStore } from '@/stores/cart';
import { useWishlistStore } from '@/stores/wishlist';
import { useThemeStore } from '@/stores/theme';

const TOP_STRIP_ROTATION_MS = 5000;
const topStripMessages = [
  'Free shipping across India on every order',
  'Thoughtfully picked toys and lunch boxes for everyday joy',
  'COD available at checkout',
  'New drops for school, play, and gifting',
];

const open = ref(false);
const shopMenuOpen = ref(false);
const activeTopStripMessage = ref(0);
const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const cart = useCartStore();
const wishlist = useWishlistStore();
const theme = useThemeStore();

let topStripTimer = null;

const profileTo = computed(() => {
  if (!auth.user) {
    return '/login';
  }

  return auth.isAdmin ? '/admin' : '/profile';
});

const shopActive = computed(
  () => route.path === '/shop' || route.path.startsWith('/category/'),
);

function openShopMenu() {
  shopMenuOpen.value = true;
}

function closeShopMenu() {
  shopMenuOpen.value = false;
}

function onShopFocusOut(event) {
  const shopNav = event.currentTarget;
  if (shopNav instanceof HTMLElement && !shopNav.contains(event.relatedTarget)) {
    closeShopMenu();
  }
}

function onShopCategoryClick() {
  shopMenuOpen.value = false;
  if (typeof document !== 'undefined' && document.activeElement instanceof HTMLElement) {
    document.activeElement.blur();
  }
}

function openCart() {
  if (cart.isEmpty) {
    router.push('/shop');
    return;
  }

  cart.openTray();
}

onMounted(() => {
  topStripTimer = window.setInterval(() => {
    activeTopStripMessage.value = (activeTopStripMessage.value + 1) % topStripMessages.length;
  }, TOP_STRIP_ROTATION_MS);
});

onUnmounted(() => {
  if (topStripTimer) {
    window.clearInterval(topStripTimer);
    topStripTimer = null;
  }
});
</script>

<template>
  <header class="site-header">
    <div class="top-strip">
      <Transition name="top-strip-message" mode="out-in">
        <span :key="activeTopStripMessage" class="top-strip__message">
          {{ topStripMessages[activeTopStripMessage] }}
        </span>
      </Transition>
    </div>
    <div class="header-main">
      <RouterLink class="brand" to="/" :aria-label="`${theme.brandName} home`">
        <img :src="brandAssets.logo" :alt="theme.brandName" />
      </RouterLink>

      <nav class="desktop-nav" aria-label="Primary navigation">
        <template v-for="item in primaryNav" :key="item.href">
          <div
            v-if="item.href === '/shop'"
            class="nav-item nav-item--shop"
            :class="{ 'is-active': shopActive, 'is-open': shopMenuOpen }"
            @mouseenter="openShopMenu"
            @mouseleave="closeShopMenu"
            @focusin="openShopMenu"
            @focusout="onShopFocusOut"
          >
            <RouterLink
              class="nav-item__trigger"
              to="/shop"
              :class="{ active: shopActive }"
              :aria-haspopup="true"
              :aria-expanded="shopMenuOpen"
            >
              <span>Shop</span>
              <ChevronDown class="nav-item__chevron" :size="14" aria-hidden="true" />
            </RouterLink>
            <Transition name="shop-nav-panel">
              <div
                v-show="shopMenuOpen"
                class="nav-item__panel"
                role="menu"
                aria-label="Shop categories"
              >
                <p class="nav-item__panel-kicker">Collections</p>
                <RouterLink
                  v-for="(child, index) in shopNavChildren"
                  :key="child.href"
                  class="nav-item__link"
                  :class="{ 'nav-item__link--featured': index === 0 }"
                  :style="{ '--nav-link-delay': `${index * 45}ms` }"
                  :to="child.href"
                  role="menuitem"
                  active-class="active"
                  @click="onShopCategoryClick"
                >
                  <span class="nav-item__link-label">{{ child.label }}</span>
                </RouterLink>
              </div>
            </Transition>
          </div>
          <RouterLink
            v-else
            :to="item.href"
            :active-class="item.href === '/' ? '' : 'active'"
            exact-active-class="active"
          >
            {{ item.label }}
          </RouterLink>
        </template>
      </nav>

      <div class="header-actions">
        <RouterLink
          class="icon-link"
          :to="profileTo"
          :aria-label="auth.user ? 'Account profile' : 'Sign in'"
        >
          <User :size="20" aria-hidden="true" />
        </RouterLink>
        <RouterLink
          class="icon-link header-wishlist-link"
          to="/wishlist"
          :aria-label="wishlist.count ? `Wishlist, ${wishlist.count} items` : 'Wishlist'"
        >
          <Heart :size="20" aria-hidden="true" />
          <CartBadge :count="wishlist.count" />
        </RouterLink>
        <button
          class="icon-link"
          type="button"
          :aria-label="cart.itemCount ? `Cart, ${cart.itemCount} items` : 'Cart'"
          @click="openCart"
        >
          <ShoppingBag :size="20" aria-hidden="true" />
          <CartBadge :count="cart.itemCount" />
        </button>
        <button
          class="icon-button mobile-menu-button"
          type="button"
          aria-label="Open menu"
          :aria-expanded="open"
          @click="open = true"
        >
          <Menu :size="22" aria-hidden="true" />
        </button>
      </div>
    </div>

    <MobileDrawer :open="open" @close="open = false" />
  </header>
</template>
