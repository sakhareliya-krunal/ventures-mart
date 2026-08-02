<script setup>
import { useHead } from '@unhead/vue';
import ComingSoon from '@/components/ui/ComingSoon.vue';
import StaticPageLayout from '@/components/ui/StaticPageLayout.vue';
import { footerContact, footerWhatsApp } from '@/constants/footer';
import { useThemeStore } from '@/stores/theme';
import { seoHeadFromServer } from '@/utils/seoHead';

const theme = useThemeStore();

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
    lead="We offer 7-day replacement support. Reach out with your order details and we will help from there."
  >
    <div class="static-highlights">
      <div class="static-highlight">
        <strong>7-day replacement support</strong>
        <p>Tell us within seven days if something arrives damaged or incorrect.</p>
      </div>
      <div class="static-highlight">
        <strong>How to start</strong>
        <p>Email, call, or WhatsApp with your order ID and a short description of the issue.</p>
      </div>
    </div>

    <div class="static-prose">
      <section>
        <h2>Contact for returns</h2>
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
    </div>

    <ComingSoon
      title="Full returns policy coming soon"
      description="Step-by-step eligibility, pickup instructions, and replacement timelines will live here shortly. Until then, contact us and we will guide you personally."
    />
  </StaticPageLayout>
</template>
