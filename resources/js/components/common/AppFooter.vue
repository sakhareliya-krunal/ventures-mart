<script setup>
import {
  ArrowUpRight,
  Mail,
  Package,
  Phone,
  ShieldCheck,
  Truck,
} from '@lucide/vue';
import { RouterLink } from 'vue-router';
import { brandAssets } from '@/constants/assets';
import {
  footerBlurb,
  footerBottomLinks,
  footerCompanyLinks,
  footerContact,
  footerFeatures,
  footerPaymentPills,
  footerShopLinks,
  footerSupportLinks,
} from '@/constants/footer';
import { useThemeStore } from '@/stores/theme';

const theme = useThemeStore();

const featureIcons = {
  truck: Truck,
  shield: ShieldCheck,
  package: Package,
};
</script>

<template>
  <footer class="site-footer">
    <div class="footer-inner">
      <div class="footer-features">
        <div
          v-for="feature in footerFeatures"
          :key="feature.label"
          class="footer-features__item"
        >
          <component :is="featureIcons[feature.icon]" :size="18" aria-hidden="true" />
          <span>{{ feature.label }}</span>
        </div>
      </div>

      <div class="footer-grid">
        <div class="footer-brand">
          <RouterLink class="brand brand--footer" to="/" :aria-label="`${theme.brandName} home`">
            <img :src="brandAssets.logoLight" :alt="theme.brandName" />
          </RouterLink>
          <p>{{ footerBlurb }}</p>
          <div class="footer-brand__actions">
            <RouterLink class="footer-btn footer-btn--shop" to="/shop">
              Browse shop
              <ArrowUpRight :size="16" aria-hidden="true" />
            </RouterLink>
          </div>
        </div>

        <div class="footer-col">
          <h3>Shop</h3>
          <RouterLink
            v-for="link in footerShopLinks"
            :key="link.label"
            :to="link.href"
          >
            {{ link.label }}
          </RouterLink>
        </div>

        <div class="footer-col">
          <h3>Support</h3>
          <RouterLink
            v-for="link in footerSupportLinks"
            :key="link.label"
            :to="link.href"
          >
            {{ link.label }}
          </RouterLink>
        </div>

        <div class="footer-col">
          <h3>Company</h3>
          <RouterLink
            v-for="link in footerCompanyLinks"
            :key="link.label"
            :to="link.href"
          >
            {{ link.label }}
          </RouterLink>
        </div>

        <aside class="footer-help" aria-labelledby="footer-help-title">
          <h3 id="footer-help-title">Need help?</h3>
          <div class="footer-help__list">
            <a
              class="footer-help__row"
              :href="`mailto:${footerContact.email}`"
              :aria-label="`Email ${footerContact.email}`"
            >
              <Mail class="footer-help__icon" :size="18" aria-hidden="true" />
              <span class="footer-help__text">
                <span class="footer-help__label">Email</span>
                <span class="footer-help__value">{{ footerContact.email }}</span>
              </span>
            </a>
            <a
              class="footer-help__row"
              :href="footerContact.phoneHref"
              :aria-label="`Call ${footerContact.phone}`"
            >
              <Phone class="footer-help__icon" :size="18" aria-hidden="true" />
              <span class="footer-help__text">
                <span class="footer-help__label">Phone</span>
                <span class="footer-help__value">{{ footerContact.phone }}</span>
              </span>
            </a>
          </div>
        </aside>
      </div>

      <div class="footer-payments">
        <div class="footer-payments__copy">
          <span>Secure payments</span>
          <RouterLink to="/payments">
            Learn more
            <ArrowUpRight :size="14" aria-hidden="true" />
          </RouterLink>
        </div>
        <div class="footer-payments__pills">
          <span v-for="pill in footerPaymentPills" :key="pill">{{ pill }}</span>
        </div>
      </div>
    </div>

    <div class="footer-bottom">
      <div class="footer-bottom__inner">
        <p>© {{ new Date().getFullYear() }} {{ theme.brandName }}. All rights reserved.</p>
        <nav class="footer-bottom__links" aria-label="Legal">
          <RouterLink
            v-for="link in footerBottomLinks"
            :key="link.label"
            :to="link.href"
          >
            {{ link.label }}
          </RouterLink>
        </nav>
      </div>
    </div>
  </footer>
</template>
