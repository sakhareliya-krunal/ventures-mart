<script setup>
import { CheckCircle2, Clock3, PackageCheck, RefreshCw } from '@lucide/vue';
import { useHead } from '@unhead/vue';
import AppButton from '@/components/ui/AppButton.vue';
import StaticPageLayout from '@/components/ui/StaticPageLayout.vue';
import { footerContact, footerWhatsApp } from '@/constants/footer';
import { useThemeStore } from '@/stores/theme';
import { seoHeadFromServer } from '@/utils/seoHead';

const theme = useThemeStore();
const sections = [
  { id: 'eligibility', label: 'Eligibility' },
  { id: 'start', label: 'How to start' },
  { id: 'review', label: 'Review process' },
  { id: 'resolution', label: 'Resolution' },
];

useHead(() =>
  seoHeadFromServer({
    title: `Replacement support | ${theme.brandName}`,
    description: `7-day replacement support for toys and lunch boxes at ${theme.brandName}.`,
    canonical: '/replacement',
  }),
);
</script>

<template>
  <StaticPageLayout
    eyebrow="Support"
    title="Replacement support"
    lead="A straightforward 7-day replacement process when an item arrives damaged, defective, or incorrect."
    :sections="sections"
    wide
  >
    <template #aside>
      <RefreshCw :size="24" aria-hidden="true" />
      <strong>7 days</strong>
      to report an eligible issue
    </template>
    <template #actions>
      <AppButton to="/orders" size="lg">Request from orders</AppButton>
      <AppButton to="/contact" variant="secondary" size="lg">Contact support</AppButton>
    </template>

    <div class="static-highlights">
      <div class="static-highlight">
        <span class="static-highlight__icon"><Clock3 :size="20" aria-hidden="true" /></span>
        <strong>7-day replacement support</strong>
        <p>Tell us within seven days if something arrives damaged or incorrect.</p>
      </div>
      <div class="static-highlight">
        <span class="static-highlight__icon"><PackageCheck :size="20" aria-hidden="true" /></span>
        <strong>Request from your order</strong>
        <p>Open the delivered order and submit a replacement request with photos when helpful.</p>
      </div>
      <div class="static-highlight">
        <span class="static-highlight__icon"><CheckCircle2 :size="20" aria-hidden="true" /></span>
        <strong>Human review</strong>
        <p>Our team reviews the details and ships an approved replacement when eligible.</p>
      </div>
    </div>

    <div class="static-prose">
      <section id="eligibility">
        <h2>What is eligible</h2>
        <p>
          Replacement support applies when an item arrives damaged, defective, materially different
          from what was ordered, or when the wrong item is delivered. Start the request within seven
          days of delivery.
        </p>
      </section>

      <section id="start">
        <h2>How to start a request</h2>
        <p>
          Sign in, open the delivered order, and choose
          <strong>Request replacement</strong>. You can also email
          <a :href="`mailto:${footerContact.email}`">{{ footerContact.email }}</a>,
          call
          <a :href="footerContact.phoneHref">{{ footerContact.phone }}</a>,
          or
          <a :href="footerWhatsApp.href" target="_blank" rel="noopener noreferrer">chat on WhatsApp</a>
          if you need help.
        </p>
      </section>

      <section id="review">
        <h2>What happens after you request</h2>
        <p>
          We verify the order and review the issue using the details and photos you provide. Keep the
          item available while we review so we can confirm the next step quickly.
        </p>
      </section>

      <section id="resolution">
        <h2>Replacement resolution</h2>
        <p>
          Once approved, we prepare a replacement shipment for the eligible item. Courier coverage and
          stock availability can affect the final timeline.
        </p>
      </section>
    </div>
  </StaticPageLayout>
</template>
