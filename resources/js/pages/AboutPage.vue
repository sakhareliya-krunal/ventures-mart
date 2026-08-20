<script setup>
import { ChevronRight } from '@lucide/vue';
import { useHead } from '@unhead/vue';
import AppButton from '@/components/ui/AppButton.vue';
import { aboutCommitment, aboutGiftCta, aboutHeritage } from '@/constants/about';
import { brandAssets } from '@/constants/assets';
import { useThemeStore } from '@/stores/theme';
import { seoHeadFromServer } from '@/utils/seoHead';

const theme = useThemeStore();

const heritageImage = brandAssets[aboutHeritage.imageKey];

useHead(() =>
  seoHeadFromServer({
    title: `About | ${theme.brandName}`,
    description: `Why ${theme.brandName} — curated toys and lunch boxes for families across India.`,
    canonical: '/about',
  }),
);
</script>

<template>
  <div class="about-page">
    <section class="about-heritage page-section" aria-labelledby="about-heritage-title">
      <div class="about-heritage__inner">
        <div class="about-heritage__media">
          <img
            :src="heritageImage"
            :alt="aboutHeritage.imageAlt"
            loading="eager"
            decoding="async"
          />
          <span class="about-heritage__badge">{{ aboutHeritage.badge }}</span>
        </div>
        <div class="about-heritage__copy">
          <span class="about-heritage__eyebrow">{{ aboutHeritage.eyebrow }}</span>
          <h1 id="about-heritage-title">{{ aboutHeritage.title }}</h1>
          <p v-for="(paragraph, index) in aboutHeritage.paragraphs" :key="index">
            {{ paragraph }}
          </p>
        </div>
      </div>
    </section>

    <section class="about-commitment" aria-labelledby="about-commitment-title">
      <div class="about-commitment__inner page-section">
        <header class="about-commitment__header">
          <span class="about-commitment__eyebrow">{{ aboutCommitment.eyebrow }}</span>
          <h2 id="about-commitment-title">{{ aboutCommitment.title }}</h2>
          <p>{{ aboutCommitment.text }}</p>
        </header>
        <div class="about-commitment__stats">
          <article
            v-for="stat in aboutCommitment.stats"
            :key="stat.value"
            class="about-commitment__stat"
          >
            <strong>{{ stat.value }}</strong>
            <p>{{ stat.label }}</p>
          </article>
        </div>
      </div>
    </section>

    <section class="about-gift-cta page-section" aria-labelledby="about-gift-cta-title">
      <div class="about-gift-cta__inner">
        <h2 id="about-gift-cta-title">{{ aboutGiftCta.title }}</h2>
        <p>{{ aboutGiftCta.text }}</p>
        <AppButton :to="aboutGiftCta.href" size="lg">
          {{ aboutGiftCta.cta }}
          <ChevronRight :size="18" />
        </AppButton>
      </div>
    </section>
  </div>
</template>
