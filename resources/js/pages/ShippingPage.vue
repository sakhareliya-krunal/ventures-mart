<script setup>
import { useHead } from '@unhead/vue';
import ComingSoon from '@/components/ui/ComingSoon.vue';
import StaticPageLayout from '@/components/ui/StaticPageLayout.vue';
import { useThemeStore } from '@/stores/theme';

const theme = useThemeStore();

useHead({
  title: () => `Shipping | ${theme.brandName}`,
});
</script>

<template>
  <StaticPageLayout
    eyebrow="Support"
    title="Shipping"
    lead="We deliver toys and lunch boxes across India. Detailed timelines and zone maps are on the way."
  >
    <div class="static-highlights">
      <div class="static-highlight">
        <strong>Delivery across India</strong>
        <p>Orders ship to addresses nationwide, subject to courier coverage.</p>
      </div>
      <div class="static-highlight">
        <strong>Pay your way</strong>
        <p>Checkout supports UPI, cards, net banking, and cash on delivery where available.</p>
      </div>
    </div>

    <div class="static-prose">
      <section>
        <h2>What to expect</h2>
        <p>
          After you place an order, we prepare it for dispatch and hand it to our delivery partners.
          You will receive updates through the contact details shared at checkout.
        </p>
      </section>
    </div>

    <ComingSoon
      title="Detailed shipping guide coming soon"
      description="We are putting together delivery timelines, zone coverage, and packing notes. Need an estimate for your pin code today? Contact us or message on WhatsApp."
    />
  </StaticPageLayout>
</template>
