<script setup>
import { Camera, CheckCircle2, Clock3, RefreshCw } from '@lucide/vue';
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
    title: `Returns | ${theme.brandName}`,
    description: `7-day replacement support for toys and lunch boxes at ${theme.brandName}.`,
    canonical: '/returns',
  }),
);
</script>

<template>
  <StaticPageLayout
    eyebrow="Support"
    title="Returns & replacements"
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
      <AppButton to="/contact" size="lg">Start a request</AppButton>
      <AppButton to="/orders" variant="secondary" size="lg">View orders</AppButton>
    </template>

    <div class="static-highlights">
      <div class="static-highlight">
        <span class="static-highlight__icon"><Clock3 :size="20" aria-hidden="true" /></span>
        <strong>7-day replacement support</strong>
        <p>Tell us within seven days if something arrives damaged or incorrect.</p>
      </div>
      <div class="static-highlight">
        <span class="static-highlight__icon"><Camera :size="20" aria-hidden="true" /></span>
        <strong>Photos help us act faster</strong>
        <p>Share clear photos of the item, packaging, and shipping label when applicable.</p>
      </div>
      <div class="static-highlight">
        <span class="static-highlight__icon"><CheckCircle2 :size="20" aria-hidden="true" /></span>
        <strong>Human review</strong>
        <p>Our support team reviews the details and explains the available next step.</p>
      </div>
    </div>

    <div class="static-prose">
      <section id="eligibility">
        <h2>What is eligible</h2>
        <p>
          Replacement support applies when an item arrives damaged, defective, materially different
          from what was ordered, or when the wrong item is delivered. Contact us within seven days
          of delivery and keep the item and original packaging available for review.
        </p>
      </section>

      <section id="start">
        <h2>How to start a request</h2>
        <p>
          Email
          <a :href="`mailto:${footerContact.email}`">{{ footerContact.email }}</a>,
          call
          <a :href="footerContact.phoneHref">{{ footerContact.phone }}</a>,
          or
          <a :href="footerWhatsApp.href" target="_blank" rel="noopener noreferrer">chat on WhatsApp</a>.
          Include photos when an item is damaged so we can resolve it faster.
        </p>
      </section>

      <section id="review">
        <h2>What happens after you contact us</h2>
        <p>
          We verify the order and review the issue using the details you provide. Please do not send
          an item back without confirmation, because the correct return or pickup route can vary.
        </p>
      </section>

      <section id="resolution">
        <h2>Replacement and resolution</h2>
        <p>
          Once approved, we will explain whether a courier pickup, another return method, or a direct
          replacement applies. Availability and courier coverage can affect the final timeline.
        </p>
      </section>
    </div>
  </StaticPageLayout>
</template>
