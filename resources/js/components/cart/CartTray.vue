<script setup>
import { computed, onBeforeUnmount, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Minus, Plus, ShoppingBag, Trash2, X } from '@lucide/vue';
import AppButton from '@/components/ui/AppButton.vue';
import { formatCurrency } from '@/utils/format';
import { requireLogin } from '@/utils/authRedirect';
import { useAuthStore } from '@/stores/auth';
import { useCartStore } from '@/stores/cart';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const cart = useCartStore();

const hiddenOnRoute = computed(
  () => route.name === 'cart' || route.name === 'checkout',
);

const visible = computed(
  () => cart.trayOpen && !cart.isEmpty && !hiddenOnRoute.value,
);

const lineLabel = computed(() => {
  const count = cart.lineCount;
  return count === 1 ? '1 item' : `${count} items`;
});

function lockScroll(locked) {
  document.body.style.overflow = locked ? 'hidden' : '';
}

function onKeydown(event) {
  if (event.key === 'Escape') {
    cart.closeTray();
  }
}

async function decrease(item) {
  const next = item.quantity - 1;
  if (next <= 0) {
    await cart.removeItem(item.product_id);
    return;
  }
  await cart.updateQuantity(item.product_id, next);
}

async function increase(item) {
  await cart.updateQuantity(item.product_id, item.quantity + 1);
}

function goToCheckout() {
  cart.closeTray();

  if (!auth.user) {
    requireLogin(router, '/checkout');
    return;
  }

  router.push('/checkout');
}

function goToCart() {
  cart.closeTray();
  router.push('/cart');
}

watch(visible, (isOpen) => {
  lockScroll(isOpen);

  if (isOpen) {
    window.addEventListener('keydown', onKeydown);
  } else {
    window.removeEventListener('keydown', onKeydown);
  }
});

watch(hiddenOnRoute, (hidden) => {
  if (hidden) {
    cart.closeTray();
  }
});

onBeforeUnmount(() => {
  lockScroll(false);
  window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
  <Teleport to="body">
    <Transition name="cart-tray">
      <div v-if="visible" class="cart-tray" role="dialog" aria-modal="true" aria-label="Cart">
        <button
          class="cart-tray__backdrop"
          type="button"
          aria-label="Close cart"
          @click="cart.closeTray()"
        />
        <div class="cart-tray__panel">
          <div class="cart-tray__header">
            <div class="cart-tray__title">
              <ShoppingBag :size="18" aria-hidden="true" />
              <div>
                <strong>Your cart</strong>
                <span>{{ lineLabel }}</span>
              </div>
            </div>
            <button
              class="cart-tray__close"
              type="button"
              aria-label="Close cart"
              @click="cart.closeTray()"
            >
              <X :size="18" />
            </button>
          </div>

          <div class="cart-tray__list">
            <div
              v-for="item in cart.items"
              :key="item.product_id"
              class="cart-tray__line"
            >
              <img
                v-if="item.product"
                :src="item.product.image"
                :alt="item.product.name"
              />
              <div class="cart-tray__meta">
                <strong>{{ item.product?.name }}</strong>
                <span>{{ formatCurrency(item.product?.price || 0) }}</span>
              </div>
              <div class="cart-tray__qty" role="group" :aria-label="`Quantity for ${item.product?.name}`">
                <button
                  type="button"
                  :aria-label="item.quantity <= 1 ? 'Remove item' : 'Decrease quantity'"
                  @click="decrease(item)"
                >
                  <Trash2 v-if="item.quantity <= 1" :size="14" />
                  <Minus v-else :size="14" />
                </button>
                <span>{{ item.quantity }}</span>
                <button
                  type="button"
                  aria-label="Increase quantity"
                  @click="increase(item)"
                >
                  <Plus :size="14" />
                </button>
              </div>
              <strong class="cart-tray__line-total">
                {{ formatCurrency((item.product?.price || 0) * item.quantity) }}
              </strong>
            </div>
          </div>

          <div class="cart-tray__footer">
            <div class="cart-tray__subtotal">
              <span>Subtotal</span>
              <strong>{{ formatCurrency(cart.totals.subtotal) }}</strong>
            </div>
            <AppButton size="lg" class="cart-tray__cta" @click="goToCheckout">
              Checkout
            </AppButton>
            <button class="cart-tray__view-cart" type="button" @click="goToCart">
              View cart
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
