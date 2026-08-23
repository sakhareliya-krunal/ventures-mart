<script setup>
import { computed } from 'vue';
import BrandSplashLoader from '@/components/ui/BrandSplashLoader.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import ToastHost from '@/components/ui/ToastHost.vue';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';

const auth = useAuthStore();
const ui = useUiStore();

const showProgress = computed(() => ui.navigating && !auth.redirecting);
const showOverlay = computed(() => auth.redirecting);
const overlayLabel = computed(() => (auth.redirecting ? 'Redirecting...' : 'Loading...'));
</script>

<template>
  <BrandSplashLoader />
  <div v-if="showProgress" class="app-route-progress" aria-hidden="true">
    <span />
  </div>
  <LoadingSpinner
    v-if="showOverlay"
    page
    overlay
    :label="overlayLabel"
  />
  <ToastHost />
  <RouterView />
</template>
