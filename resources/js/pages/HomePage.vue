<script setup>
import { computed, onMounted } from 'vue';
import { useHead } from '@unhead/vue';
import HeroBanner from '@/components/home/HeroBanner.vue';
import CategoryPillars from '@/components/home/CategoryPillars.vue';
import ProductGrid from '@/components/product/ProductGrid.vue';
import SectionHeader from '@/components/ui/SectionHeader.vue';
import { useCategoriesStore } from '@/stores/categories';
import { useProductsStore } from '@/stores/products';
import { useThemeStore } from '@/stores/theme';

const theme = useThemeStore();
const categories = useCategoriesStore();
const products = useProductsStore();

useHead({
  title: () => `${theme.brandName} | Toys & lunch boxes`,
});

const lunchPicks = computed(() =>
  products.featured.filter((product) => product.category === 'lunch-box').slice(0, 4),
);

const toyPicks = computed(() =>
  products.featured.filter((product) => product.category === 'toys').slice(0, 4),
);

onMounted(async () => {
  await Promise.all([
    categories.list.length ? Promise.resolve() : categories.fetchAll(),
    products.fetchFeatured(),
    products.fetchSale(),
  ]);
});
</script>

<template>
  <HeroBanner />
  <CategoryPillars />

  <section class="page-section">
    <SectionHeader
      title="Steel & kids lunch boxes"
      description="Durable tiffins for school and everyday packing."
      :action="{ label: 'Shop lunch boxes', href: '/category/lunch-box' }"
    />
    <ProductGrid :products="lunchPicks" />
  </section>

  <section class="page-section page-section--soft">
    <SectionHeader
      title="Toys for play"
      description="Building, pretend play, and soft companions for every day."
      :action="{ label: 'Shop toys', href: '/category/toys' }"
    />
    <ProductGrid :products="toyPicks" />
  </section>

  <section v-if="products.sale.length" class="page-section page-section--tinted">
    <SectionHeader
      title="On sale"
      description="Limited savings on toys and lunch boxes."
      :action="{ label: 'Shop all', href: '/shop' }"
    />
    <ProductGrid :products="products.sale" />
  </section>
</template>
