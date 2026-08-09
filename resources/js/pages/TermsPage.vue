<script setup>
import { Scale } from '@lucide/vue';
import { RouterLink } from 'vue-router';
import { useHead } from '@unhead/vue';
import AppButton from '@/components/ui/AppButton.vue';
import StaticPageLayout from '@/components/ui/StaticPageLayout.vue';
import { footerContact } from '@/constants/footer';
import { useThemeStore } from '@/stores/theme';
import { seoHeadFromServer } from '@/utils/seoHead';

const theme = useThemeStore();
const sections = [
  { id: 'overview', label: 'Overview' },
  { id: 'orders', label: 'Orders & pricing' },
  { id: 'availability', label: 'Availability' },
  { id: 'accounts', label: 'Accounts' },
  { id: 'use', label: 'Acceptable use' },
  { id: 'replacement', label: 'Replacement' },
  { id: 'limitation', label: 'Limitation' },
  { id: 'contact', label: 'Contact' },
];

useHead(() =>
  seoHeadFromServer({
    title: `Terms of Service | ${theme.brandName}`,
    description: `Terms for shopping toys and lunch boxes on ${theme.brandName} across India.`,
    canonical: '/terms',
  }),
);
</script>

<template>
  <StaticPageLayout
    eyebrow="Terms"
    title="Terms of Service"
    lead="Simple terms for shopping toys and lunch boxes on Ventures Mart across India."
    meta="Last updated: July 2026"
    :sections="sections"
    wide
  >
    <template #aside>
      <Scale :size="24" aria-hidden="true" />
      <strong>Clear terms</strong>
      for a transparent shopping experience
    </template>
    <template #actions>
      <AppButton to="/shop" size="lg">Browse the shop</AppButton>
      <AppButton to="/contact" variant="secondary" size="lg">Ask a question</AppButton>
    </template>
    <div class="static-prose">
      <section id="overview">
        <h2>Store overview</h2>
        <p>
          Ventures Mart is an online store for curated toys and everyday lunch boxes, delivered
          across India. By browsing or placing an order, you agree to these terms.
        </p>
      </section>

      <section id="orders">
        <h2>Orders and pricing</h2>
        <p>
          Prices are shown in Indian Rupees (INR). An order is confirmed once payment is completed
          or COD is accepted at checkout. We may cancel or adjust an order if an item is priced
          incorrectly, unavailable, or cannot be fulfilled.
        </p>
      </section>

      <section id="availability">
        <h2>Availability</h2>
        <p>
          Product details, stock, and offers can change as inventory updates. If something becomes
          unavailable after you order, we will contact you with the next steps.
        </p>
      </section>

      <section id="accounts">
        <h2>Accounts</h2>
        <p>
          You are responsible for keeping login details secure and for activity under your account.
          Provide accurate contact and delivery information so we can fulfill your orders.
        </p>
      </section>

      <section id="use">
        <h2>Acceptable use</h2>
        <p>
          Do not misuse the storefront, attempt unauthorized access, or submit false information.
          We may suspend access if we detect abuse or fraudulent activity.
        </p>
      </section>

      <section id="replacement">
        <h2>Replacement support</h2>
        <p>
          We offer 7-day replacement support for eligible issues after delivery. See our
          <RouterLink to="/replacement">Replacement support</RouterLink>
          page, or request a replacement from your order tracking page.
        </p>
      </section>

      <section id="limitation">
        <h2>Limitation</h2>
        <p>
          To the extent allowed by law, Ventures Mart is not liable for indirect or consequential
          losses arising from use of the storefront or delays outside our reasonable control.
        </p>
      </section>

      <section id="contact">
        <h2>Contact</h2>
        <p>
          Questions about these terms?
          Email
          <a :href="`mailto:${footerContact.email}`">{{ footerContact.email }}</a>
          or call
          <a :href="footerContact.phoneHref">{{ footerContact.phone }}</a>.
        </p>
      </section>
    </div>
  </StaticPageLayout>
</template>
