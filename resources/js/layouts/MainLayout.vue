<script setup>
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
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
const router = useRouter();
const mainRef = ref(null);
const welcomeMessage = ref('');
const auth = useAuthStore();
const cart = useCartStore();
const wishlist = useWishlistStore();
const categories = useCategoriesStore();

function consumeWelcomeQuery() {
  if (String(route.query.welcome || '') !== '1') return;

  welcomeMessage.value = "Welcome back! You've been signed in.";
  const { welcome: _welcome, ...rest } = route.query;
  router.replace({ path: route.path, query: rest });
}

onMounted(() => {
  Promise.all([
    auth.fetchUser(),
    cart.fetch(),
    wishlist.fetch(),
    categories.fetchAll(),
  ]);

  initScrollReveal(mainRef.value);
  consumeWelcomeQuery();
});

watch(
  () => route.fullPath,
  async () => {
    consumeWelcomeQuery();
    await nextTick();
    refreshScrollReveal(mainRef.value || document);
  },
);

onUnmounted(() => {
  destroyScrollReveal();
});
</script>

<template>
  <div class="app-shell" :class="{ 'app-shell--home': route.name === 'home' }">
    <AppHeader />
    <main ref="mainRef">
      <p v-if="welcomeMessage" class="form-success layout-welcome" role="status">
        {{ welcomeMessage }}
      </p>
      <RouterView />
    </main>
    <AppFooter />
    <CartTray />
    <WhatsAppFloat />
  </div>
</template>
