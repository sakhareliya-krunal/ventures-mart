<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import BrandSplashLoader from '@/components/ui/BrandSplashLoader.vue';
import RouteChangeLoader from '@/components/ui/RouteChangeLoader.vue';
import ToastHost from '@/components/ui/ToastHost.vue';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';

const route = useRoute();
const auth = useAuthStore();
const ui = useUiStore();

const showRouteLoader = computed(() => (ui.navigating && !route.path.startsWith('/admin')) || auth.redirecting);
const routeLoaderLabel = computed(() => (auth.redirecting ? 'Redirecting' : 'Loading page'));
</script>

<template>
  <BrandSplashLoader />
  <RouteChangeLoader :active="showRouteLoader" :label="routeLoaderLabel" />
  <ToastHost />
  <RouterView />
</template>
