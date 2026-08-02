<script setup>
import { computed } from 'vue';
import BrandSplashLoader from '@/components/ui/BrandSplashLoader.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import ToastHost from '@/components/ui/ToastHost.vue';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';

const auth = useAuthStore();
const ui = useUiStore();

const showOverlay = computed(() => ui.navigating || auth.redirecting);
const overlayLabel = computed(() => {
  if (auth.redirecting && !ui.navigating) {
    return 'Redirecting…';
  }
  return 'Loading…';
});
</script>

<template>
  <BrandSplashLoader v-if="ui.splashVisible" />
  <LoadingSpinner
    v-if="showOverlay"
    page
    overlay
    :label="overlayLabel"
  />
  <ToastHost />
  <RouterView />
</template>
