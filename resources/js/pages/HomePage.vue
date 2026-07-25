<script setup>
import { computed, onMounted } from 'vue';
import { useHead } from '@unhead/vue';
import HeroBanner from '@/components/home/HeroBanner.vue';
import CategoryPillars from '@/components/home/CategoryPillars.vue';
import ServiceStrip from '@/components/home/ServiceStrip.vue';
import OfferBanner from '@/components/home/OfferBanner.vue';
import ProductGrid from '@/components/product/ProductGrid.vue';
import SectionHeader from '@/components/ui/SectionHeader.vue';
import { useCategoriesStore } from '@/stores/categories';
import { useProductsStore } from '@/stores/products';
import { useThemeStore } from '@/stores/theme';

const theme = useThemeStore();
const categories = useCategoriesStore();
const products = useProductsStore();

useHead({
  title: () => `${theme.brandName} | Premium toys & lunch boxes`,
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
  <ServiceStrip />

  <section class="page-section">
    <SectionHeader
      eyebrow="Lunch Box"
      title="Steel & kids favorites"
      description="Durable tiffins and colorful kids boxes curated for everyday packing."
      :action="{ label: 'Shop lunch boxes', href: '/category/lunch-box' }"
    />
    <ProductGrid :products="lunchPicks" />
  </section>

  <section class="page-section page-section--soft">
    <SectionHeader
      eyebrow="Toys"
      title="Play picks"
      description="Hands-on toys that make building, cooking play, and cuddles part of the day."
      :action="{ label: 'Shop toys', href: '/category/toys' }"
    />
    <ProductGrid :products="toyPicks" />
  </section>

  <OfferBanner />

  <section class="page-section page-section--tinted">
    <SectionHeader
      eyebrow="Offers"
      title="Sale favorites"
      description="Limited savings on toys and lunch boxes families love."
      :action="{ label: 'View shop', href: '/shop' }"
    />
    <ProductGrid :products="products.sale" />
  </section>
</template>
