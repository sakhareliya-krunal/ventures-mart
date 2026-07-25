<script setup>
import { useHead } from '@unhead/vue';
import StaticPageLayout from '@/components/ui/StaticPageLayout.vue';
import { footerContact } from '@/constants/footer';
import { useThemeStore } from '@/stores/theme';

const theme = useThemeStore();

useHead({
  title: () => `Privacy Policy | ${theme.brandName}`,
});
</script>

<template>
  <StaticPageLayout
    eyebrow="Privacy"
    title="Privacy Policy"
    lead="How Venture Smart collects and uses information when you browse, shop, or reach out for support."
    meta="Last updated: July 2026"
  >
    <div class="static-prose">
      <section>
        <h2>What we collect</h2>
        <p>
          Depending on how you use the store, we may process account details (name, email, phone),
          cart and wishlist items, order and delivery information, and messages you send through the
          contact form or WhatsApp.
        </p>
      </section>

      <section>
        <h2>Cookies and session</h2>
        <p>
          We use session cookies so you stay signed in and so your cart and wishlist remain available
          as you move between pages. These cookies are needed for the storefront to work as expected.
        </p>
      </section>

      <section>
        <h2>How we use your data</h2>
        <ul>
          <li>Process and fulfill orders for toys and lunch boxes</li>
          <li>Keep your account, cart, and wishlist in sync</li>
          <li>Respond to support and product questions</li>
          <li>Improve the browsing and checkout experience</li>
        </ul>
      </section>

      <section>
        <h2>Sharing</h2>
        <p>
          We share personal information only when it is needed to fulfill an order or provide
          support—for example with delivery partners. We do not sell your data.
        </p>
      </section>

      <section>
        <h2>Retention</h2>
        <p>
          Order and support records are kept for as long as needed for fulfillment, returns, and
          legal or accounting requirements. You can ask us about data we hold for your account.
        </p>
      </section>

      <section>
        <h2>Your choices</h2>
        <p>
          You can update account details after signing in, clear cart or wishlist items, and contact
          us to ask about access or correction of personal information we store for support.
        </p>
      </section>

      <section>
        <h2>Contact</h2>
        <p>
          Questions about privacy?
          Email
          <a :href="`mailto:${footerContact.email}`">{{ footerContact.email }}</a>
          or call
          <a :href="footerContact.phoneHref">{{ footerContact.phone }}</a>.
        </p>
      </section>
    </div>
  </StaticPageLayout>
</template>
