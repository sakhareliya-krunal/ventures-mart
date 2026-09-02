<script setup>
import { ref } from 'vue';
import ProductCard from '@/components/product/ProductCard.vue';
import SkeletonCard from '@/components/ui/SkeletonCard.vue';
import api from '@/services/api';
import { useWhenVisible } from '@/composables/useWhenVisible';
import { unwrapData } from '@/utils/format';

const products = ref([]);
const loading = ref(false);
const error = ref('');
const sectionEl = ref(null);

async function fetchBestSellers() {
  loading.value = true;
  error.value = '';

  try {
    const { data } = await api.get('/products', {
      params: {
        sort: 'rating',
      },
    });

    products.value = (unwrapData(data) || []).slice(0, 10);
  } catch {
    products.value = [];
    error.value = 'Unable to load best sellers right now.';
  } finally {
    loading.value = false;
  }
}

useWhenVisible(sectionEl, fetchBestSellers);
</script>

<template>
  <section ref="sectionEl" class="home-best-sellers page-section" aria-labelledby="home-best-sellers-title">
    <div class="home-best-sellers__header">
      <h2 id="home-best-sellers-title">Best Seller</h2>
    </div>

    <div v-if="loading" class="home-best-sellers__rail" aria-label="Loading best sellers">
      <SkeletonCard v-for="index in 6" :key="index" />
    </div>

    <p v-else-if="error" class="home-best-sellers__empty">
      {{ error }}
    </p>

    <div
      v-else-if="products.length"
      class="home-best-sellers__rail"
    >
      <ProductCard
        v-for="(product, index) in products"
        :key="product.id"
        :product="product"
        :eager="index < 2"
      />
    </div>

    <p v-else class="home-best-sellers__empty">
      No best sellers found right now.
    </p>
  </section>
</template>

<style scoped>
.home-best-sellers {
  overflow: hidden;
}

.home-best-sellers__header {
  align-items: end;
  display: flex;
  gap: 1rem;
  justify-content: center;
  margin-bottom: 1.2rem;
  position: relative;
  text-align: center;
}

.home-best-sellers__header h2 {
  font-size: clamp(1.65rem, 3vw, 2.15rem);
  letter-spacing: 0;
  line-height: 1.14;
  margin: 0.35rem 0 0;
}

.home-best-sellers__rail {
  -webkit-overflow-scrolling: touch;
  display: grid;
  gap: 0.9rem;
  grid-auto-columns: clamp(11.25rem, 21vw, 15.5rem);
  grid-auto-flow: column;
  justify-content: safe center;
  margin-inline: calc(clamp(1rem, 4vw, 1.25rem) * -1);
  min-width: 0;
  overflow-x: auto;
  overscroll-behavior-x: contain;
  touch-action: pan-x pan-y pinch-zoom;
  padding: 0.1rem clamp(1rem, 4vw, 1.25rem) 0.6rem;
  scroll-padding-inline: clamp(1rem, 4vw, 1.25rem);
  scroll-snap-type: x proximity;
  scrollbar-width: none;
}

.home-best-sellers__rail::-webkit-scrollbar {
  display: none;
}

.home-best-sellers__rail > :deep(*) {
  min-width: 0;
  scroll-snap-align: start;
}

.home-best-sellers__empty {
  background: var(--color-surface-soft);
  border: 1px dashed var(--color-border);
  border-radius: var(--radius-sm);
  color: rgba(28, 44, 76, 0.72);
  font-weight: 700;
  margin: 0;
  padding: 1.35rem;
  text-align: center;
}

@media (max-width: 720px) {
  .home-best-sellers__rail {
    grid-auto-columns: clamp(10.5rem, 78vw, 15.5rem);
  }
}
</style>
