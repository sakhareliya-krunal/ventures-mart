<script setup>
import { computed } from 'vue';
import BrandSplashLoader from '@/components/ui/BrandSplashLoader.vue';
import RouteChangeLoader from '@/components/ui/RouteChangeLoader.vue';
import ToastHost from '@/components/ui/ToastHost.vue';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';

const auth = useAuthStore();
const ui = useUiStore();

const showRouteLoader = computed(() => ui.navigating || auth.redirecting);
const routeLoaderLabel = computed(() => (auth.redirecting ? 'Redirecting' : 'Loading page'));
</script>

<template>
  <BrandSplashLoader />
  <RouteChangeLoader :active="showRouteLoader" :label="routeLoaderLabel" />
  <ToastHost />
  <RouterView />
</template>