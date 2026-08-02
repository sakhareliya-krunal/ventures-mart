<script setup>
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useHead } from '@unhead/vue';
import CartLineItem from '@/components/cart/CartLineItem.vue';
import OrderSummary from '@/components/cart/OrderSummary.vue';
import AppButton from '@/components/ui/AppButton.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import PageHero from '@/components/ui/PageHero.vue';
import { useAuthStore } from '@/stores/auth';
import { useCartStore } from '@/stores/cart';
import { useThemeStore } from '@/stores/theme';
import { requireLogin } from '@/utils/authRedirect';
import { seoHeadFromServer } from '@/utils/seoHead';

const theme = useThemeStore();
const auth = useAuthStore();
const cart = useCartStore();
const router = useRouter();

useHead(() =>
  seoHeadFromServer({
    title: `Cart | ${theme.brandName}`,
    description: `Review your ${theme.brandName} cart.`,
    canonical: '/cart',
    robots: 'noindex,follow',
  }),
);

onMounted(() => cart.fetch());

function goToCheckout() {
  if (!auth.user) {
    requireLogin(router, '/checkout');
    return;
  }

  router.push('/checkout');
}
</script>

<template>
  <LoadingSpinner v-if="cart.loading" page />
  <EmptyState
    v-else-if="!cart.items.length"
    title="Your cart is empty"
    description="Add a few favorites from the catalog to start checkout."
    action-label="Shop products"
  />
  <section v-else class="cart-page">
    <PageHero
      eyebrow="Cart"
      title="Review your order"
      lead="Check quantities and totals before you continue to checkout."
      size="compact"
    />
    <div class="page-section">
      <div class="checkout-layout">
        <div class="cart-list">
          <CartLineItem v-for="item in cart.items" :key="item.product_id" :item="item" />
        </div>
        <div>
          <OrderSummary :totals="cart.totals" />
          <AppButton size="lg" class="full-width" @click="goToCheckout">Checkout</AppButton>
        </div>
      </div>
    </div>
  </section>
</template>
