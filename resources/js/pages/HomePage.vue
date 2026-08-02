<script setup>
import { computed, onMounted } from 'vue';
import { useHead } from '@unhead/vue';
import CategoryPillars from '@/components/home/CategoryPillars.vue';
import HeroBanner from '@/components/home/HeroBanner.vue';
import HomeTrustQuotes from '@/components/home/HomeTrustQuotes.vue';
import HomeWhyStrip from '@/components/home/HomeWhyStrip.vue';
import OfferBanner from '@/components/home/OfferBanner.vue';
import ServiceStrip from '@/components/home/ServiceStrip.vue';
import ProductGrid from '@/components/product/ProductGrid.vue';
import SectionHeader from '@/components/ui/SectionHeader.vue';
import { homeRails } from '@/constants/home';
import { useCategoriesStore } from '@/stores/categories';
import { useProductsStore } from '@/stores/products';
import { useThemeStore } from '@/stores/theme';
import { seoHeadFromServer } from '@/utils/seoHead';

const theme = useThemeStore();
const categories = useCategoriesStore();
const products = useProductsStore();

useHead(() =>
  seoHeadFromServer({
    title: `${theme.brandName} | Toys & lunch boxes`,
    description:
      'Shop curated toys and steel lunch boxes for school, play, and everyday family life across India.',
    canonical: '/',
  }),
);

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
  <ServiceStrip />
  <CategoryPillars />

  <section v-if="lunchPicks.length" class="page-section">
    <SectionHeader
      :title="homeRails.lunch.title"
      :description="homeRails.lunch.description"
      :action="homeRails.lunch.action"
    />
    <ProductGrid :products="lunchPicks" />
  </section>

  <OfferBanner />

  <section v-if="toyPicks.length" class="page-section page-section--soft">
    <SectionHeader
      :title="homeRails.toys.title"
      :description="homeRails.toys.description"
      :action="homeRails.toys.action"
    />
    <ProductGrid :products="toyPicks" />
  </section>

  <HomeWhyStrip />
  <HomeTrustQuotes />

  <section v-if="products.sale.length" class="page-section page-section--tinted">
    <SectionHeader
      :title="homeRails.sale.title"
      :description="homeRails.sale.description"
      :action="homeRails.sale.action"
    />
    <ProductGrid :products="products.sale" />
  </section>
</template>
