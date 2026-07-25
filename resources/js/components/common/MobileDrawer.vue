<script setup>
import { onBeforeUnmount, watch } from 'vue';
import { useRouter } from 'vue-router';
import { X } from '@lucide/vue';
import { RouterLink } from 'vue-router';
import { brandAssets } from '@/constants/assets';
import { primaryNav } from '@/constants/navigation';
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
const router = useRouter();
const auth = useAuthStore();
const cart = useCartStore();
const theme = useThemeStore();

function close() {
  emit('close');
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

function lockScroll(locked) {
  document.body.style.overflow = locked ? 'hidden' : '';
}

watch(
  () => props.open,
  (isOpen) => {
    lockScroll(isOpen);

    if (isOpen) {
      window.addEventListener('keydown', onKeydown);
    } else {
      window.removeEventListener('keydown', onKeydown);
    }
  },
);

onBeforeUnmount(() => {
  lockScroll(false);
  window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
  <Teleport to="body">
    <Transition name="mobile-drawer">
      <div v-if="open" class="mobile-drawer">
        <button
          class="mobile-drawer__backdrop"
          type="button"
          aria-label="Close menu"
          @click="close"
        />
        <div class="mobile-panel" role="dialog" aria-modal="true" aria-label="Menu">
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
            <RouterLink
              v-for="item in primaryNav"
              :key="item.href"
              :to="item.href"
              :active-class="item.href === '/' ? '' : 'active'"
              exact-active-class="active"
              @click="close"
            >
              {{ item.label }}
            </RouterLink>
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
