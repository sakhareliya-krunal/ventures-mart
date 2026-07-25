<script setup>
import ProductCard from '@/components/product/ProductCard.vue';
import SkeletonCard from '@/components/ui/SkeletonCard.vue';

defineProps({
  products: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
});
</script>

<template>
  <div v-if="loading" class="product-grid">
    <SkeletonCard v-for="index in 8" :key="index" />
  </div>
  <div v-else-if="!products.length" class="empty-state">
    <h2>No products found</h2>
    <p>Try a different search term or clear the filters.</p>
  </div>
  <div v-else class="product-grid">
    <ProductCard v-for="product in products" :key="product.id" :product="product" />
  </div>
</template>
