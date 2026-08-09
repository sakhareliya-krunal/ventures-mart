<script setup>
import { BadgeCheck, CreditCard, LockKeyhole, WalletCards } from '@lucide/vue';
import { useHead } from '@unhead/vue';
import AppButton from '@/components/ui/AppButton.vue';
import StaticPageLayout from '@/components/ui/StaticPageLayout.vue';
import { footerPaymentPills } from '@/constants/footer';
import { useThemeStore } from '@/stores/theme';
import { seoHeadFromServer } from '@/utils/seoHead';

const theme = useThemeStore();
const sections = [
  { id: 'methods', label: 'Payment methods' },
  { id: 'confirmation', label: 'Confirmation' },
  { id: 'failed', label: 'Failed payments' },
  { id: 'refunds', label: 'Refunds' },
];

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
    :sections="sections"
    wide
  >
    <template #aside>
      <LockKeyhole :size="24" aria-hidden="true" />
      <strong>Secure</strong>
      online checkout via Razorpay
    </template>
    <template #actions>
      <AppButton to="/shop" size="lg">Shop securely</AppButton>
      <AppButton to="/contact" variant="secondary" size="lg">Payment help</AppButton>
    </template>

    <div class="static-highlights">
      <div class="static-highlight">
        <span class="static-highlight__icon"><WalletCards :size="20" aria-hidden="true" /></span>
        <strong>Flexible payment options</strong>
        <p>Use popular online methods or cash on delivery where it is available.</p>
      </div>
      <div class="static-highlight">
        <span class="static-highlight__icon"><LockKeyhole :size="20" aria-hidden="true" /></span>
        <strong>Protected checkout</strong>
        <p>Online payment details are handled by Razorpay’s secure payment flow.</p>
      </div>
      <div class="static-highlight">
        <span class="static-highlight__icon"><BadgeCheck :size="20" aria-hidden="true" /></span>
        <strong>Clear confirmation</strong>
        <p>Your order page reflects the confirmed payment and order status.</p>
      </div>
    </div>

    <div class="static-prose">
      <section id="methods">
        <h2>Accepted methods</h2>
        <p>
          Ventures Mart checkout supports the payment options below. Online payments are processed
          securely through Razorpay.
        </p>
        <div class="payment-pills">
          <span v-for="pill in footerPaymentPills" :key="pill" class="payment-pill">{{ pill }}</span>
        </div>
      </section>

      <section id="confirmation">
        <h2>Order confirmation</h2>
        <p>
          Prepaid orders are confirmed after a successful payment. COD orders are confirmed when
          checkout completes and will be collected upon delivery where COD is offered.
        </p>
      </section>

      <section id="failed">
        <h2>Failed or interrupted payments</h2>
        <p>
          If a payment does not complete, check your order page before trying again. A bank may show
          a temporary pending entry even when the order was not confirmed. Contact us with your
          order number if the status remains unclear.
        </p>
      </section>

      <section id="refunds">
        <h2>Refunds</h2>
        <p>
          When a refund is approved, it is returned through the applicable original payment route.
          Bank and payment-provider processing times may vary after the refund is initiated.
        </p>
      </section>
    </div>

    <div class="static-support-note">
      <CreditCard :size="28" aria-hidden="true" />
      <h2>Need help with a payment?</h2>
      <p>Share your order number and payment status—never send card details, PINs, or OTPs.</p>
      <div class="static-support-note__actions">
        <AppButton to="/contact" variant="secondary">Contact support</AppButton>
      </div>
    </div>
  </StaticPageLayout>
</template>
