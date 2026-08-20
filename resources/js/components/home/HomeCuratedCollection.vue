<script setup>
import { onMounted, ref } from 'vue';
import ProductCard from '@/components/product/ProductCard.vue';
import SkeletonCard from '@/components/ui/SkeletonCard.vue';
import { homeCurated } from '@/constants/home';
import api from '@/services/api';
import { unwrapData } from '@/utils/format';

const products = ref([]);
const loading = ref(false);

async function fetchCurated() {
  loading.value = true;

  try {
    const { data } = await api.get('/products/featured');
    products.value = (unwrapData(data) || [])
      .slice()
      .sort((a, b) => {
        const ratingDelta = Number(b.rating || 0) - Number(a.rating || 0);
        if (ratingDelta !== 0) return ratingDelta;
        return Number(b.reviews || 0) - Number(a.reviews || 0);
      })
      .slice(0, 4);
  } catch {
    products.value = [];
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  fetchCurated();
});
</script>

<template>
  <section
    v-if="loading || products.length"
    class="home-curated page-section"
    aria-labelledby="home-curated-title"
  >
    <div class="home-curated__header">
      <h2 id="home-curated-title">{{ homeCurated.title }}</h2>
    </div>

    <div v-if="loading" class="home-curated__rail" aria-label="Loading curated collection">
      <SkeletonCard v-for="index in 4" :key="index" />
    </div>

    <div v-else class="home-curated__rail">
      <ProductCard
        v-for="product in products"
        :key="product.id"
        :product="product"
      />
    </div>
  </section>
</template>

<style scoped>
.home-curated {
  overflow: hidden;
}

.home-curated__header {
  margin-bottom: 1.2rem;
  text-align: center;
}

.home-curated__header h2 {
  font-size: clamp(1.65rem, 3vw, 2.15rem);
  letter-spacing: 0;
  line-height: 1.14;
  margin: 0.35rem 0 0;
}

.home-curated__rail {
  display: grid;
  gap: 0.9rem;
  grid-auto-columns: clamp(11.25rem, 21vw, 15.5rem);
  grid-auto-flow: column;
  justify-content: safe center;
  margin-inline: calc(clamp(1rem, 4vw, 1.25rem) * -1);
  overflow-x: auto;
  overscroll-behavior-x: contain;
  padding: 0.1rem clamp(1rem, 4vw, 1.25rem) 0.6rem;
  scroll-padding-inline: clamp(1rem, 4vw, 1.25rem);
  scroll-snap-type: x proximity;
  scrollbar-width: none;
}

.home-curated__rail::-webkit-scrollbar {
  display: none;
}

.home-curated__rail > :deep(*) {
  min-width: 0;
  scroll-snap-align: start;
}

@media (max-width: 720px) {
  .home-curated__rail {
    gap: 0.72rem;
    grid-auto-columns: min(58vw, 13.5rem);
    justify-content: start;
    scroll-snap-type: x mandatory;
  }
}
</style>
