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
  const count = cart.quantityCount;
  return count === 1 ? '1 item' : `${count} items`;
});

const showIgst = computed(() => Number(cart.totals.igst || 0) > 0);
const isEstimate = computed(() => cart.totals.tax_type === 'estimate');

function lockScroll(locked) {
  document.body.style.overflow = locked ? 'hidden' : '';
}

function onKeydown(event) {
  if (event.key === 'Escape') {
    cart.closeTray();
  }
}

function decrease(item) {
  cart.bumpQuantity(item.product_id, -1);
}

function increase(item) {
  cart.bumpQuantity(item.product_id, 1);
}

function atMaxQuantity(item) {
  const stock = Number(item.product?.stock);
  const stockCap = Number.isFinite(stock) && stock > 0 ? stock : 99;
  return item.quantity >= Math.min(99, stockCap);
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
              <div
                class="cart-tray__qty"
                :class="{ 'is-syncing': cart.isSyncing(item.product_id) }"
                role="group"
                :aria-label="`Quantity for ${item.product?.name}`"
              >
                <button
                  type="button"
                  :aria-label="item.quantity <= 1 ? 'Remove item' : 'Decrease quantity'"
                  @click="decrease(item)"
                >
                  <Trash2 v-if="item.quantity <= 1" :size="16" />
                  <Minus v-else :size="16" />
                </button>
                <span>{{ item.quantity }}</span>
                <button
                  type="button"
                  aria-label="Increase quantity"
                  :disabled="atMaxQuantity(item)"
                  @click="increase(item)"
                >
                  <Plus :size="16" />
                </button>
              </div>
              <strong class="cart-tray__line-total">
                {{ formatCurrency((item.product?.price || 0) * item.quantity) }}
              </strong>
            </div>
          </div>

          <div class="cart-tray__footer">
            <div class="cart-tray__totals">
              <div class="cart-tray__row">
                <span>Subtotal</span>
                <strong>{{ formatCurrency(cart.totals.subtotal) }}</strong>
              </div>
              <div class="cart-tray__row">
                <span>Shipping</span>
                <strong>{{ cart.totals.shipping ? formatCurrency(cart.totals.shipping) : 'Free' }}</strong>
              </div>
              <template v-if="showIgst">
                <div class="cart-tray__row">
                  <span>IGST (5%)</span>
                  <strong>{{ formatCurrency(cart.totals.igst) }}</strong>
                </div>
              </template>
              <template v-else>
                <div class="cart-tray__row">
                  <span>CGST (2.5%)</span>
                  <strong>{{ formatCurrency(cart.totals.cgst ?? (cart.totals.tax || 0) / 2) }}</strong>
                </div>
                <div class="cart-tray__row">
                  <span>SGST (2.5%)</span>
                  <strong>{{ formatCurrency(cart.totals.sgst ?? (cart.totals.tax || 0) / 2) }}</strong>
                </div>
              </template>
              <div class="cart-tray__row cart-tray__row--total">
                <span>Total</span>
                <strong>{{ formatCurrency(cart.totals.total) }}</strong>
              </div>
              <p v-if="isEstimate" class="cart-tray__gst-note">
                Estimated until shipping state is set
              </p>
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
