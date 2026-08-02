<script setup>
import { useHead } from '@unhead/vue';
import ComingSoon from '@/components/ui/ComingSoon.vue';
import StaticPageLayout from '@/components/ui/StaticPageLayout.vue';
import { footerPaymentPills } from '@/constants/footer';
import { useThemeStore } from '@/stores/theme';
import { seoHeadFromServer } from '@/utils/seoHead';

const theme = useThemeStore();

useHead(() =>
  seoHeadFromServer({
    title: `Payments | ${theme.brandName}`,
    description: `Secure online payments and COD options for shopping at ${theme.brandName}.`,
    canonical: '/payments',
  }),
);
</script>

<template>
  <StaticPageLayout
    eyebrow="Company"
    title="Payments"
    lead="Secure checkout for toys and lunch boxes—pay online or choose cash on delivery where available."
  >
    <div class="static-prose">
      <section>
        <h2>Accepted methods</h2>
        <p>
          Ventures Mart checkout supports the payment options below. Online payments are processed
          securely through Razorpay.
        </p>
        <div class="payment-pills">
          <span v-for="pill in footerPaymentPills" :key="pill" class="payment-pill">{{ pill }}</span>
        </div>
      </section>

      <section>
        <h2>Order confirmation</h2>
        <p>
          Prepaid orders are confirmed after a successful payment. COD orders are confirmed when
          checkout completes and will be collected upon delivery where COD is offered.
        </p>
      </section>
    </div>

    <ComingSoon
      title="Payment FAQ coming soon"
      description="We are preparing answers about failed payments, refunds to original methods, and Razorpay receipt questions. Need help with a charge today? Contact us."
    />
  </StaticPageLayout>
</template>
