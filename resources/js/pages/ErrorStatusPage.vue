<script setup>
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useHead } from '@unhead/vue';
import AppButton from '@/components/ui/AppButton.vue';
import { brandAssets } from '@/constants/assets';
import { useThemeStore } from '@/stores/theme';

const props = defineProps({
  code: {
    type: [Number, String],
    default: null,
  },
});

const route = useRoute();
const router = useRouter();
const theme = useThemeStore();

const statusCode = computed(() => {
  const raw = props.code ?? route.params.code ?? route.meta.errorCode ?? 404;
  const num = Number(raw);
  return [403, 404, 419, 429, 500, 503].includes(num) ? num : 404;
});

const copy = computed(() => {
  const map = {
    403: {
      title: 'Access denied',
      body: "You don't have permission to view this page. Head home or keep shopping our catalog.",
      showShop: true,
      showRetry: false,
    },
    404: {
      title: 'Page not found',
      body: "That page isn't part of the Ventures Mart storefront. Try the shop or return home.",
      showShop: true,
      showRetry: false,
    },
    419: {
      title: 'Session expired',
      body: 'Your session timed out for security. Refresh and continue where you left off.',
      showShop: false,
      showRetry: true,
    },
    429: {
      title: 'Too many requests',
      body: 'Please wait a moment before trying again.',
      showShop: false,
      showRetry: true,
    },
    500: {
      title: 'Something went wrong',
      body: "We're sorry — something unexpected happened. You can retry, go home, or continue shopping.",
      showShop: true,
      showRetry: true,
    },
    503: {
      title: 'Temporarily unavailable',
      body: 'Ventures Mart is briefly unavailable. Please try again in a little while.',
      showShop: false,
      showRetry: true,
    },
  };

  return map[statusCode.value] || map[404];
});

useHead({
  title: () => `${copy.value.title} | ${theme.brandName}`,
});

function retry() {
  router.go(0);
}
</script>

<template>
  <section class="error-status page-section">
    <div class="error-status__card">
      <img
        class="error-status__logo"
        :src="brandAssets.logo"
        :alt="theme.brandName"
        width="160"
        height="48"
      />
      <p class="error-status__code">{{ statusCode }}</p>
      <h1>{{ copy.title }}</h1>
      <p class="error-status__body">{{ copy.body }}</p>
      <div class="error-status__actions">
        <AppButton v-if="copy.showRetry" type="button" @click="retry">Retry</AppButton>
        <AppButton to="/" :variant="copy.showRetry ? 'ghost' : undefined">Home</AppButton>
        <AppButton v-if="copy.showShop" to="/shop" variant="ghost">Continue shopping</AppButton>
      </div>
    </div>
  </section>
</template>

<style scoped>
.error-status {
  align-items: center;
  display: flex;
  justify-content: center;
  min-height: min(70vh, 36rem);
  padding: 2rem 1rem 3rem;
}

.error-status__card {
  background:
    radial-gradient(ellipse 70% 50% at 15% 0%, rgba(230, 30, 77, 0.08), transparent 55%),
    radial-gradient(ellipse 60% 45% at 90% 100%, rgba(11, 46, 138, 0.08), transparent 50%),
    var(--color-surface, #fff);
  border: 1px solid var(--color-border, #e2e8f0);
  border-radius: 1.25rem;
  box-shadow: var(--shadow-sm, 0 8px 28px rgba(7, 31, 99, 0.08));
  max-width: 32rem;
  padding: 2rem 1.5rem 1.75rem;
  text-align: center;
  width: min(100%, 32rem);
}

.error-status__logo {
  display: inline-block;
  height: auto;
  margin: 0 auto 1rem;
  max-width: 10rem;
  object-fit: contain;
}

.error-status__code {
  color: var(--color-primary, #0b2e8a);
  font-size: 0.85rem;
  font-weight: 800;
  letter-spacing: 0.12em;
  margin: 0 0 0.5rem;
  text-transform: uppercase;
}

.error-status h1 {
  font-size: clamp(1.45rem, 3vw, 1.85rem);
  font-weight: 900;
  letter-spacing: -0.03em;
  margin: 0 0 0.65rem;
}

.error-status__body {
  color: var(--color-muted, rgba(28, 44, 76, 0.72));
  font-size: 0.98rem;
  line-height: 1.55;
  margin: 0 auto 1.35rem;
  max-width: 28rem;
}

.error-status__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.65rem;
  justify-content: center;
}

@media (max-width: 520px) {
  .error-status__card {
    padding: 1.5rem 1.1rem 1.35rem;
  }

  .error-status__actions {
    flex-direction: column;
  }

  .error-status__actions :deep(.app-button),
  .error-status__actions :deep(a) {
    width: 100%;
  }
}
</style>
