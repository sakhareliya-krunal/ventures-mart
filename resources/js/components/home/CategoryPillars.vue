<script setup>
import { ChevronRight } from '@lucide/vue';
import { RouterLink } from 'vue-router';
import { brandAssets } from '@/constants/assets';

defineProps({
  eyebrow: {
    type: String,
    default: 'Shop categories',
  },
  title: {
    type: String,
    default: 'Toys and lunch boxes',
  },
  subtitle: {
    type: String,
    default: 'Pick a category to browse playtime finds or everyday tiffins.',
  },
});

const pillars = [
  {
    title: 'Toys',
    text: 'Creative play picks for building, pretend kitchens, and soft companions.',
    href: '/category/toys',
    image: brandAssets.pillarToys,
  },
  {
    title: 'Lunch Box',
    text: 'Steel and kids tiffins made for school, office, and everyday packing.',
    href: '/category/lunch-box',
    image: brandAssets.pillarLunchBox,
  },
];
</script>

<template>
  <section class="category-pillars page-section">
    <div class="category-pillars__intro">
      <span class="eyebrow">{{ eyebrow }}</span>
      <h2>{{ title }}</h2>
      <p>{{ subtitle }}</p>
    </div>
    <div class="category-pillars__grid">
      <RouterLink
        v-for="pillar in pillars"
        :key="pillar.href"
        class="category-pillar"
        :to="pillar.href"
      >
        <div class="category-pillar__media">
          <img :src="pillar.image" :alt="pillar.title" loading="lazy" />
        </div>
        <div class="category-pillar__copy">
          <h3>{{ pillar.title }}</h3>
          <p>{{ pillar.text }}</p>
          <span class="category-pillar__link">
            Explore
            <ChevronRight :size="16" />
          </span>
        </div>
      </RouterLink>
    </div>
  </section>
</template>
