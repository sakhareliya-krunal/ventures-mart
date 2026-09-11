<script setup>
import {
  Award,
  Building2,
  CreditCard,
  Headphones,
  Mail,
  MessageCircle,
  Phone,
  RefreshCw,
  ShoppingBag,
  Truck,
} from '@lucide/vue';
import FooterPaymentMarks from '@/components/common/FooterPaymentMarks.vue';
import { computed } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { brandAssets } from '@/constants/assets';
import {
  footerBottomLinks,
  footerBottomTagline,
  footerCompanyLinks,
  footerContact,
  footerCustomerCareLinks,
  footerDescription,
  footerFeatures,
  footerShopLinks,
  footerTagline,
  footerWhatsApp,
} from '@/constants/footer';
import { useThemeStore } from '@/stores/theme';

const route = useRoute();
const theme = useThemeStore();

const isProductRoute = computed(() => route.path.startsWith('/product/'));

const featureIcons = {
  award: Award,
  card: CreditCard,
  refresh: RefreshCw,
  truck: Truck,
};

const footerColumns = computed(() => [
  {
    label: 'Shop',
    icon: ShoppingBag,
    tone: 'pink',
    links: footerShopLinks,
  },
  {
    label: 'Customer Care',
    icon: Headphones,
    tone: 'green',
    links: footerCustomerCareLinks,
  },
  {
    label: 'Company',
    icon: Building2,
    tone: 'yellow',
    links: footerCompanyLinks,
  },
]);

const contactLinks = computed(() => [
  {
    label: 'WhatsApp',
    value: footerWhatsApp.label,
    href: footerWhatsApp.href,
    icon: MessageCircle,
    external: true,
  },
  {
    label: 'Email',
    value: footerContact.email,
    href: `mailto:${footerContact.email}`,
    icon: Mail,
    isEmail: true,
  },
  {
    label: 'Phone',
    value: footerContact.phone,
    href: footerContact.phoneHref,
    icon: Phone,
  },
]);
</script>

<template>
  <footer
    class="site-footer"
    :class="{ 'site-footer--product-sticky': isProductRoute }"
  >
    <div class="footer-shell">
      <div class="footer-orb footer-orb--pink" aria-hidden="true"></div>
      <div class="footer-orb footer-orb--blue" aria-hidden="true"></div>
      <div class="footer-orb footer-orb--yellow" aria-hidden="true"></div>

      <div class="footer-inner">
        <section class="footer-top" aria-label="About the store">
          <div class="footer-brand-panel">
            <RouterLink class="brand brand--footer" to="/" :aria-label="`${theme.brandName} home`">
              <img :src="brandAssets.logo" :alt="theme.brandName" />
            </RouterLink>
            <p class="footer-brand-panel__tagline">{{ footerTagline }}</p>
            <p class="footer-brand-panel__description">{{ footerDescription }}</p>
          </div>
        </section>

        <section class="footer-columns" aria-label="Footer navigation">
          <nav
            v-for="column in footerColumns"
            :key="column.label"
            class="footer-col"
            :aria-label="`Footer ${column.label} links`"
          >
            <h3 class="footer-col__heading">
              <span
                class="footer-col__heading-icon"
                :class="`footer-col__heading-icon--${column.tone}`"
                aria-hidden="true"
              >
                <component :is="column.icon" :size="20" />
              </span>
              <span>{{ column.label }}</span>
            </h3>
            <RouterLink
              v-for="link in column.links"
              :key="link.label"
              active-class=""
              exact-active-class=""
              :to="link.href"
            >
              <span>{{ link.label }}</span>
            </RouterLink>
          </nav>

          <address class="footer-col footer-col--contact" aria-label="Get in touch">
            <h3 class="footer-col__heading">
              <span class="footer-col__heading-icon footer-col__heading-icon--pink" aria-hidden="true">
                <Phone :size="20" />
              </span>
              <span>Get In Touch</span>
            </h3>
            <a
              v-for="link in contactLinks"
              :key="link.label"
              :class="{ 'footer-col__contact-value--email': link.isEmail }"
              :href="link.href"
              :target="link.external ? '_blank' : undefined"
              :rel="link.external ? 'noopener noreferrer' : undefined"
              :aria-label="`${link.label}: ${link.value}`"
            >
              <component :is="link.icon" :size="16" aria-hidden="true" />
              <span>{{ link.value }}</span>
            </a>
          </address>
        </section>

        <section class="footer-benefits" aria-label="Shopping benefits">
          <div
            v-for="feature in footerFeatures"
            :key="feature.label"
            class="footer-benefit"
            :class="`footer-benefit--${feature.tone}`"
          >
            <span class="footer-benefit__icon" aria-hidden="true">
              <component :is="featureIcons[feature.icon]" :size="20" />
            </span>
            <span class="footer-benefit__copy">
              <span class="footer-benefit__label">{{ feature.label }}</span>
              <span class="footer-benefit__description">{{ feature.description }}</span>
            </span>
          </div>
        </section>

        <div class="footer-bottom-card">
          <div class="footer-bottom-card__legal">
            <div class="footer-bottom-card__copy-block">
              <p class="footer-bottom-card__copy">
                &copy; 2026 {{ theme.brandName }}. All rights reserved.
              </p>
              <p class="footer-bottom-card__tagline">{{ footerBottomTagline }}</p>
            </div>

            <nav class="footer-bottom-card__links" aria-label="Legal">
              <RouterLink
                v-for="link in footerBottomLinks"
                :key="link.label"
                active-class=""
                exact-active-class=""
                :to="link.href"
              >
                <span>{{ link.label }}</span>
              </RouterLink>
            </nav>
          </div>

          <div class="footer-bottom-card__payments">
            <span class="footer-bottom-card__accept-label">We accept</span>
            <FooterPaymentMarks />
          </div>

          <p class="footer-bottom-card__made">
            <span>Made with care in India</span>
            <span class="footer-bottom-card__flag" aria-hidden="true">
              <span class="footer-bottom-card__flag-wheel"></span>
            </span>
          </p>
        </div>
      </div>
    </div>
  </footer>
</template>
