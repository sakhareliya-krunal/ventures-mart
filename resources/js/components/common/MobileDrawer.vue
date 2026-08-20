<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ChevronDown, X } from '@lucide/vue';
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
            <RouterLink class="mobile-panel__brand" to="/" :aria-label="`${theme.brandName} home`" @click="close">
              <img :src="brandAssets.logo" :alt="theme.brandName" />
            </RouterLink>
            <button
              class="icon-button mobile-panel__close"
              type="button"
              aria-label="Close menu"
              @click="close"
            >
              <X :size="20" />
            </button>
          </div>
          <nav aria-label="Mobile navigation">
            <template v-for="item in primaryNav" :key="item.href">
              <div v-if="item.href === '/shop'" class="mobile-shop">
                <button
                  class="mobile-shop__toggle"
                  type="button"
                  :aria-expanded="shopExpanded"
                  aria-label="Shop categories"
                  @click="shopExpanded = !shopExpanded"
                >
                  <span>Shop</span>
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
                      {{ child.label }}
                    </RouterLink>
                  </div>
                </Transition>
              </div>
              <RouterLink
                v-else
                :to="item.href"
                :active-class="item.href === '/' ? '' : 'active'"
                exact-active-class="active"
                @click="close"
              >
                {{ item.label }}
              </RouterLink>
            </template>
            <RouterLink to="/wishlist" active-class="active" @click="close">Wishlist</RouterLink>
            <RouterLink v-if="auth.isAdmin" to="/admin" active-class="active" @click="close">
              Admin
            </RouterLink>
            <button class="mobile-panel__cart" type="button" @click="openCart">Cart</button>
          </nav>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
