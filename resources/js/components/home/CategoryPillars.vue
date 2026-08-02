<script setup>
import { ChevronRight } from '@lucide/vue';
import { RouterLink } from 'vue-router';
import { brandAssets } from '@/constants/assets';
import { homeCategories, homePillars } from '@/constants/home';

defineProps({
  eyebrow: {
    type: String,
    default: homeCategories.eyebrow,
  },
  title: {
    type: String,
    default: homeCategories.title,
  },
  subtitle: {
    type: String,
    default: homeCategories.subtitle,
  },
});

const pillars = homePillars.map((pillar) => ({
  ...pillar,
  image: pillar.href.includes('lunch')
    ? brandAssets.pillarLunchBox
    : brandAssets.pillarToys,
}));
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
