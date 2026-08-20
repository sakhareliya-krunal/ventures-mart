<script setup>
import { useHead } from '@unhead/vue';
import HeroBanner from '@/components/home/HeroBanner.vue';
import HomeBestSellers from '@/components/home/HomeBestSellers.vue';
import HomeBenefits from '@/components/home/HomeBenefits.vue';
import HomeCategoryProducts from '@/components/home/HomeCategoryProducts.vue';
import HomeCuratedCollection from '@/components/home/HomeCuratedCollection.vue';
import { useThemeStore } from '@/stores/theme';
import { seoHeadFromServer } from '@/utils/seoHead';

const theme = useThemeStore();

useHead(() =>
  seoHeadFromServer({
    title: `${theme.brandName} | Premium Stainless Steel Lunch Boxes Online in India`,
    description:
      'Buy premium stainless steel lunch boxes for office, school and kids. Leak-proof, BPA-free and durable lunch boxes with fast delivery across India.',
    canonical: '/',
  }),
);
</script>

<template>
  <HeroBanner />
  <HomeCategoryProducts />
  <HomeBestSellers />
  <HomeCuratedCollection />
  <HomeBenefits />
</template>
