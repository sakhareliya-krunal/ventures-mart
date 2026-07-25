<script setup>
import { Clock3 } from '@lucide/vue';
import AppButton from '@/components/ui/AppButton.vue';
import { footerWhatsApp } from '@/constants/footer';

defineProps({
  title: {
    type: String,
    default: 'Coming soon',
  },
  description: {
    type: String,
    default: 'We are finishing the details for this page. Reach out if you need help right away.',
  },
});
</script>

<template>
  <div class="coming-soon">
    <span class="coming-soon__icon" aria-hidden="true">
      <Clock3 :size="22" />
    </span>
    <div class="coming-soon__copy">
      <strong>{{ title }}</strong>
      <p>{{ description }}</p>
    </div>
    <div class="coming-soon__actions">
      <AppButton to="/contact" size="sm">Contact us</AppButton>
      <a
        class="button button--secondary button--sm"
        :href="footerWhatsApp.href"
        target="_blank"
        rel="noopener noreferrer"
      >
        WhatsApp
      </a>
    </div>
  </div>
</template>
