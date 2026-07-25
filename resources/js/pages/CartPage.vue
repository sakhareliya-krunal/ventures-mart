<script setup>
import { onMounted } from 'vue';
import { useHead } from '@unhead/vue';
import CartLineItem from '@/components/cart/CartLineItem.vue';
import OrderSummary from '@/components/cart/OrderSummary.vue';
import AppButton from '@/components/ui/AppButton.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import PageHero from '@/components/ui/PageHero.vue';
import { useCartStore } from '@/stores/cart';
import { useThemeStore } from '@/stores/theme';

const theme = useThemeStore();
const cart = useCartStore();

useHead({
  title: () => `Cart | ${theme.brandName}`,
});

onMounted(() => cart.fetch());
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
          <AppButton to="/checkout" size="lg" class="full-width">Checkout</AppButton>
        </div>
      </div>
    </div>
  </section>
</template>
