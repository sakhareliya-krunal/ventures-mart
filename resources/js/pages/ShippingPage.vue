<script setup>
import { Clock3, MapPinned, PackageCheck, Truck } from '@lucide/vue';
import { useHead } from '@unhead/vue';
import AppButton from '@/components/ui/AppButton.vue';
import StaticPageLayout from '@/components/ui/StaticPageLayout.vue';
import { useThemeStore } from '@/stores/theme';
import { seoHeadFromServer } from '@/utils/seoHead';

const theme = useThemeStore();
const sections = [
  { id: 'dispatch', label: 'Dispatch' },
  { id: 'delivery', label: 'Delivery' },
  { id: 'tracking', label: 'Tracking' },
  { id: 'address', label: 'Address changes' },
];

useHead(() =>
  seoHeadFromServer({
    title: `Shipping | ${theme.brandName}`,
    description: `Delivery across India for toys and lunch boxes from ${theme.brandName}.`,
    canonical: '/shipping',
  }),
);
</script>

<template>
  <StaticPageLayout
    eyebrow="Support"
    title="Shipping made simple"
    lead="Clear delivery support for toys and lunch boxes shipped across India—from confirmation to your doorstep."
    :sections="sections"
    wide
  >
    <template #aside>
      <Truck :size="24" aria-hidden="true" />
      <strong>Free</strong>
      shipping on every order
    </template>
    <template #actions>
      <AppButton to="/shop" size="lg">Start shopping</AppButton>
      <AppButton to="/contact" variant="secondary" size="lg">Ask about delivery</AppButton>
    </template>

    <div class="static-highlights">
      <div class="static-highlight">
        <span class="static-highlight__icon"><MapPinned :size="20" aria-hidden="true" /></span>
        <strong>Delivery across India</strong>
        <p>Orders ship to addresses nationwide, subject to courier coverage.</p>
      </div>
      <div class="static-highlight">
        <span class="static-highlight__icon"><PackageCheck :size="20" aria-hidden="true" /></span>
        <strong>Tracked fulfillment</strong>
        <p>Shipment updates and tracking details appear with your order when available.</p>
      </div>
      <div class="static-highlight">
        <span class="static-highlight__icon"><Clock3 :size="20" aria-hidden="true" /></span>
        <strong>Careful dispatch</strong>
        <p>Every order is checked and packed before it is handed to the courier.</p>
      </div>
    </div>

    <div class="static-prose">
      <section id="dispatch">
        <h2>Order processing and dispatch</h2>
        <p>
          Once your order is confirmed, our team checks availability, prepares the items, and packs
          them for transit. Dispatch timing can vary during weekends, holidays, and high-demand
          periods.
        </p>
      </section>

      <section id="delivery">
        <h2>Delivery coverage and estimates</h2>
        <p>
          We deliver across India where courier service is available. Delivery estimates depend on
          the destination pin code and are best treated as estimates rather than guaranteed dates.
        </p>
      </section>

      <section id="tracking">
        <h2>Tracking your order</h2>
        <p>
          When the courier assigns tracking details, you can follow the shipment from your order
          page. We also use the email and phone number provided at checkout for important updates.
        </p>
      </section>

      <section id="address">
        <h2>Address changes and delivery issues</h2>
        <p>
          Contact us as soon as possible if an address needs correction. Once a parcel is handed to
          the courier, rerouting may not be possible. For a delayed or damaged parcel, share your
          order number so our support team can investigate quickly.
        </p>
      </section>
    </div>

    <div class="static-support-note">
      <h2>Need a delivery answer?</h2>
      <p>Send your pin code or order number and our support team will help with the next step.</p>
      <div class="static-support-note__actions">
        <AppButton to="/contact" variant="secondary">Contact support</AppButton>
      </div>
    </div>
  </StaticPageLayout>
</template>
