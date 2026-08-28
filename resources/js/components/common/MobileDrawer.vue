<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import {
  ChevronDown,
  Heart,
  Home,
  Info,
  Newspaper,
  Phone,
  Shield,
  ShoppingBag,
  ShoppingCart,
  X,
} from '@lucide/vue';
import { RouterLink } from 'vue-router';
import { brandAssets } from '@/constants/assets';
import { primaryNav, shopNavChildren } from '@/constants/navigation';
import { useScrollLock } from '@/composables/useScrollLock';
import { useAuthStore } from '@/stores/auth';
import { useCartStore } from '@/stores/cart';
import { useThemeStore } from '@/stores/theme';

const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['close']);
const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const cart = useCartStore();
const theme = useThemeStore();
const shopExpanded = ref(false);
const navIconMap = {
  '/': Home,
  '/shop': ShoppingBag,
  '/about': Info,
  '/blog': Newspaper,
  '/contact': Phone,
};

function close() {
  emit('close');
}

function closeShopAndDrawer() {
  shopExpanded.value = false;
  close();
}

function openCart() {
  close();

  if (cart.isEmpty) {
    router.push('/shop');
    return;
  }

  cart.openTray();
}

function onKeydown(event) {
  if (event.key === 'Escape') {
    close();
  }
}

useScrollLock('mobile-drawer', () => props.open);

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      window.addEventListener('keydown', onKeydown);
    } else {
      window.removeEventListener('keydown', onKeydown);
      shopExpanded.value = false;
    }
  },
  { immediate: true },
);

watch(
  () => route.fullPath,
  () => {
    if (props.open) {
      close();
    }
  },
);

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
  <Teleport to="body">
    <Transition name="mobile-drawer">
      <div v-if="open" id="mobile-navigation-drawer" class="mobile-drawer">
        <button
          class="mobile-drawer__backdrop"
          type="button"
          aria-label="Close menu"
          @click="close"
        />
        <div class="mobile-panel" role="dialog" aria-modal="true" aria-label="Menu" tabindex="-1">
          <div class="mobile-panel__top">
            <div class="mobile-panel__brand-wrap">
              <RouterLink class="mobile-panel__brand" to="/" :aria-label="`${theme.brandName} home`" @click="close">
                <img :src="brandAssets.logo" :alt="theme.brandName" />
              </RouterLink>
            </div>
            <button
              class="icon-button mobile-panel__close"
              type="button"
              aria-label="Close menu"
              @click="close"
            >
              <X :size="20" aria-hidden="true" />
            </button>
          </div>
          <nav aria-label="Mobile navigation">
            <template v-for="item in primaryNav" :key="item.href">
              <div v-if="item.href === '/shop'" class="mobile-shop">
                <button
                  class="mobile-shop__toggle mobile-panel__nav-row"
                  type="button"
                  :aria-expanded="shopExpanded"
                  aria-label="Shop categories"
                  @click="shopExpanded = !shopExpanded"
                >
                  <span class="mobile-panel__nav-main">
                    <span class="mobile-panel__nav-icon" aria-hidden="true">
                      <ShoppingBag :size="18" />
                    </span>
                    <span>Shop</span>
                  </span>
                  <ChevronDown
                    class="mobile-shop__chevron"
                    :class="{ 'is-open': shopExpanded }"
                    :size="18"
                    aria-hidden="true"
                  />
                </button>
                <Transition name="mobile-shop-panel">
                  <div v-if="shopExpanded" class="mobile-shop__children">
                    <p class="mobile-shop__kicker">Collections</p>
                    <RouterLink
                      v-for="(child, index) in shopNavChildren"
                      :key="child.href"
                      :class="{ 'mobile-shop__link--featured': index === 0 }"
                      :to="child.href"
                      active-class="active"
                      @click="closeShopAndDrawer"
                    >
                      <span class="mobile-panel__nav-icon" aria-hidden="true">
                        <ShoppingBag :size="16" />
                      </span>
                      <span>{{ child.label }}</span>
                    </RouterLink>
                  </div>
                </Transition>
              </div>
              <RouterLink
                v-else
                class="mobile-panel__nav-row"
                :to="item.href"
                :active-class="item.href === '/' ? '' : 'active'"
                exact-active-class="active"
                @click="close"
              >
                <span class="mobile-panel__nav-icon" aria-hidden="true">
                  <component :is="navIconMap[item.href]" :size="18" />
                </span>
                <span>{{ item.label }}</span>
              </RouterLink>
            </template>
            <RouterLink class="mobile-panel__nav-row" to="/wishlist" active-class="active" @click="close">
              <span class="mobile-panel__nav-icon" aria-hidden="true">
                <Heart :size="18" />
              </span>
              <span>Wishlist</span>
            </RouterLink>
            <RouterLink v-if="auth.isAdmin" class="mobile-panel__nav-row" to="/admin" active-class="active" @click="close">
              <span class="mobile-panel__nav-icon" aria-hidden="true">
                <Shield :size="18" />
              </span>
              <span>Admin</span>
            </RouterLink>
            <button class="mobile-panel__cart mobile-panel__nav-row" type="button" @click="openCart">
              <span class="mobile-panel__nav-icon" aria-hidden="true">
                <ShoppingCart :size="18" />
              </span>
              <span>Cart</span>
            </button>
          </nav>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
