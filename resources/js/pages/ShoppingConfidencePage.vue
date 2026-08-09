<script setup>
import { ChevronRight, CreditCard, MapPin, RefreshCw, Truck } from '@lucide/vue';
import { useHead } from '@unhead/vue';
import { RouterLink } from 'vue-router';
import AppButton from '@/components/ui/AppButton.vue';
import Breadcrumb from '@/components/ui/Breadcrumb.vue';
import { useThemeStore } from '@/stores/theme';
import { seoHeadFromServer } from '@/utils/seoHead';

const theme = useThemeStore();

useHead(() =>
  seoHeadFromServer({
    title: `Shopping with confidence | ${theme.brandName}`,
    description:
      'How delivery across India, free shipping on all orders, 7-day replacement, and secure payments work at Ventures Mart.',
    canonical: '/shopping-confidence-shipping-replacement',
  }),
);

const sections = [
  {
    id: 'delivery',
    eyebrow: 'Delivery',
    title: 'Delivery across India',
    text: 'We ship toys and lunch boxes nationwide, subject to courier coverage. After you place an order, we prepare it for dispatch and share updates through the contact details you provide at checkout.',
    link: { to: '/shipping', label: 'Shipping details' },
    icon: MapPin,
    tone: 'soft',
  },
  {
    id: 'free-shipping',
    eyebrow: 'Shipping',
    title: 'Free shipping on all orders',
    text: 'Shipping is free on every order—no minimum spend. Stock up on a focused catalog without an extra delivery fee.',
    link: { to: '/shop', label: 'Browse the shop' },
    icon: Truck,
    tone: 'plain',
  },
  {
    id: 'replacement',
    eyebrow: 'Support',
    title: '7-day replacement support',
    text: 'If something arrives damaged or incorrect, reach out within seven days with your order details. We will guide you on next steps—so you can order kids’ essentials with a calmer mind.',
    link: { to: '/returns', label: 'Returns & replacement' },
    icon: RefreshCw,
    tone: 'soft',
  },
  {
    id: 'payments',
    eyebrow: 'Checkout',
    title: 'Pay the way that suits you',
    text: 'Checkout supports UPI, cards, net banking, COD where available, and Razorpay for secure online payments—clear options without inventing fine print.',
    link: { to: '/payments', label: 'Payment options' },
    icon: CreditCard,
    tone: 'plain',
  },
];
</script>

<template>
  <div class="article-premium">
    <div class="page-section article-premium__crumb-wrap">
      <Breadcrumb
        :items="[
          { label: 'Home', to: '/' },
          { label: 'Shopping with confidence' },
        ]"
      />
    </div>
    <section class="article-premium__hero" aria-labelledby="confidence-hero-title">
      <div class="article-premium__hero-media" aria-hidden="true">
        <img src="/images/home5-info1.png" alt="" role="presentation" />
      </div>
      <div class="article-premium__hero-scrim" aria-hidden="true" />
      <div class="article-premium__hero-inner page-section">
        <div class="article-premium__hero-copy">
          <p class="article-premium__brand">{{ theme.brandName }}</p>
          <h1 id="confidence-hero-title">Shopping with confidence</h1>
          <p class="article-premium__lead">
            Calm support from add-to-cart through delivery—nationwide shipping, clear thresholds, and
            7-day replacement when something isn’t right.
          </p>
          <div class="article-premium__actions">
            <AppButton to="/shop" size="lg">
              Shop collection
              <ChevronRight :size="18" />
            </AppButton>
            <AppButton to="/contact" variant="secondary" size="lg">Contact us</AppButton>
          </div>
        </div>
      </div>
    </section>

    <section
      v-for="section in sections"
      :id="section.id"
      :key="section.id"
      class="article-premium__section"
      :class="`article-premium__section--${section.tone}`"
      :aria-labelledby="`${section.id}-title`"
    >
      <div class="page-section article-premium__section-inner">
        <span class="article-premium__section-icon" aria-hidden="true">
          <component :is="section.icon" :size="22" />
        </span>
        <span class="eyebrow">{{ section.eyebrow }}</span>
        <h2 :id="`${section.id}-title`">{{ section.title }}</h2>
        <p>{{ section.text }}</p>
        <RouterLink class="article-premium__section-link" :to="section.link.to">
          {{ section.link.label }}
          <ChevronRight :size="16" />
        </RouterLink>
      </div>
    </section>

    <section class="article-premium__close" aria-labelledby="confidence-close-title">
      <div class="page-section article-premium__close-inner">
        <span class="eyebrow">Ready when you are</span>
        <h2 id="confidence-close-title">Explore toys and lunch boxes</h2>
        <p>
          Start with
          <RouterLink to="/category/toys">toys</RouterLink>
          or
          <RouterLink to="/category/lunch-box">lunch boxes</RouterLink>,
          or browse the full catalog.
        </p>
        <div class="article-premium__close-actions">
          <AppButton to="/shop" size="lg">
            Shop collection
            <ChevronRight :size="18" />
          </AppButton>
          <AppButton to="/contact" variant="secondary" size="lg">Ask a question</AppButton>
        </div>
      </div>
    </section>
  </div>
</template>
