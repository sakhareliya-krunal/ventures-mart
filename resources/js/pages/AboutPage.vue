<script setup>
import { ChevronRight, HeartHandshake, Package, ShieldCheck, Sparkles, Truck } from '@lucide/vue';
import { useHead } from '@unhead/vue';
import AppButton from '@/components/ui/AppButton.vue';
import CategoryPillars from '@/components/home/CategoryPillars.vue';
import PageHero from '@/components/ui/PageHero.vue';
import { footerContact } from '@/constants/footer';
import { useThemeStore } from '@/stores/theme';
import { seoHeadFromServer } from '@/utils/seoHead';

const theme = useThemeStore();

useHead(() =>
  seoHeadFromServer({
    title: `About | ${theme.brandName}`,
    description: `Why ${theme.brandName} — curated toys and lunch boxes for families across India.`,
    canonical: '/about',
  }),
);

const values = [
  {
    title: 'Curated catalog',
    text: 'A focused mix of toys and lunch boxes—chosen for daily family use, not endless clutter.',
    icon: Package,
  },
  {
    title: 'Delivery across India',
    text: 'Practical packing and shipping nationwide, with free shipping on all orders.',
    icon: Truck,
  },
  {
    title: '7-day replacement',
    text: 'Clear support if something arrives damaged or incorrect—so you can shop with confidence.',
    icon: ShieldCheck,
  },
];
</script>

<template>
  <div class="about-page">
    <PageHero
      eyebrow="Why Ventures Mart"
      :title="theme.brandName"
      lead="A focused store for creative toys and everyday lunch boxes—curated for school, play, and family life across India."
    >
      <template #actions>
        <AppButton to="/shop" size="lg">
          Shop collection
          <ChevronRight :size="18" />
        </AppButton>
        <AppButton to="/contact" variant="secondary" size="lg">Contact us</AppButton>
      </template>
      <template #aside>
        <Sparkles :size="24" aria-hidden="true" />
        <strong>Thoughtfully curated</strong>
        for real family routines
      </template>
    </PageHero>

    <section class="page-section about-story" aria-labelledby="about-story-title">
      <div class="about-story__copy">
        <span class="eyebrow">Our point of view</span>
        <h2 id="about-story-title">Fewer, better choices for everyday family life</h2>
        <p>
          Ventures Mart brings playtime and mealtime essentials into one easy-to-browse store. We
          focus on products that feel useful, joyful, and simple to choose—backed by clear delivery
          and responsive support.
        </p>
      </div>
      <div class="about-story__visual" aria-label="Serving families across India">
        <HeartHandshake :size="36" aria-hidden="true" />
        <strong>India-wide</strong>
        <span>Thoughtful products, securely packed and supported from order to delivery.</span>
      </div>
    </section>

    <CategoryPillars
      eyebrow="What we sell"
      title="Two collections. One clear purpose."
      subtitle="Start with playtime or mealtime—both built for real everyday routines."
    />

    <section class="page-section about-values">
      <div class="about-section-intro">
        <span class="eyebrow">How we work</span>
        <h2>Practical by design</h2>
        <p>Simple promises that match how families actually shop and get support.</p>
      </div>
      <div class="about-values__grid">
        <article v-for="value in values" :key="value.title" class="about-value">
          <span class="about-value__icon" aria-hidden="true">
            <component :is="value.icon" :size="22" />
          </span>
          <h3>{{ value.title }}</h3>
          <p>{{ value.text }}</p>
        </article>
      </div>
    </section>

    <section class="page-section about-cta">
      <div class="about-cta__copy">
        <span class="eyebrow">Next step</span>
        <h2>Ready to explore the catalog?</h2>
        <p>
          Browse toys and lunch boxes, or reach us at
          <a :href="`mailto:${footerContact.email}`">{{ footerContact.email }}</a>
          ·
          <a :href="footerContact.phoneHref">{{ footerContact.phone }}</a>
        </p>
        <div class="about-cta__actions">
          <AppButton to="/shop" size="lg">
            Shop collection
            <ChevronRight :size="18" />
          </AppButton>
          <AppButton to="/contact" variant="secondary" size="lg">Contact us</AppButton>
        </div>
      </div>
    </section>
  </div>
</template>
