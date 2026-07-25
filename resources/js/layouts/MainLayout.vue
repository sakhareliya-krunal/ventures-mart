<script setup>
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import AppHeader from '@/components/common/AppHeader.vue';
import AppFooter from '@/components/common/AppFooter.vue';
import CartTray from '@/components/cart/CartTray.vue';
import WhatsAppFloat from '@/components/common/WhatsAppFloat.vue';
import { useAuthStore } from '@/stores/auth';
import { useCartStore } from '@/stores/cart';
import { useWishlistStore } from '@/stores/wishlist';
import { useCategoriesStore } from '@/stores/categories';
import {
  destroyScrollReveal,
  initScrollReveal,
  refreshScrollReveal,
} from '@/utils/scrollReveal';

const route = useRoute();
const mainRef = ref(null);
const auth = useAuthStore();
const cart = useCartStore();
const wishlist = useWishlistStore();
const categories = useCategoriesStore();

onMounted(() => {
  Promise.all([
    auth.fetchUser(),
    cart.fetch(),
    wishlist.fetch(),
    categories.fetchAll(),
  ]);

  initScrollReveal(mainRef.value);
});

watch(
  () => route.fullPath,
  async () => {
    await nextTick();
    refreshScrollReveal(mainRef.value || document);
  },
);

onUnmounted(() => {
  destroyScrollReveal();
});
</script>

<template>
  <div class="app-shell">
    <AppHeader />
    <main ref="mainRef">
      <RouterView />
    </main>
    <AppFooter />
    <CartTray />
    <WhatsAppFloat />
  </div>
</template>
