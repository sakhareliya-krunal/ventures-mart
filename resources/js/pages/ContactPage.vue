<script setup>
import { reactive, ref } from 'vue';
import { Mail, MessageCircle, Phone } from '@lucide/vue';
import { useHead } from '@unhead/vue';
import AppButton from '@/components/ui/AppButton.vue';
import FormField from '@/components/ui/FormField.vue';
import PageHero from '@/components/ui/PageHero.vue';
import { footerContact, footerWhatsApp } from '@/constants/footer';
import api from '@/services/api';
import { useThemeStore } from '@/stores/theme';
import { seoHeadFromServer } from '@/utils/seoHead';

const theme = useThemeStore();
const error = ref('');
const success = ref('');
const submitting = ref(false);
const form = reactive({
  name: '',
  email: '',
  message: '',
});

const channels = [
  {
    key: 'email',
    label: 'Email',
    value: footerContact.email,
    href: `mailto:${footerContact.email}`,
    helper: 'Best for order details and longer questions',
    icon: Mail,
    external: false,
  },
  {
    key: 'phone',
    label: 'Phone',
    value: footerContact.phone,
    href: footerContact.phoneHref,
    helper: 'Call for quick order or product support',
    icon: Phone,
    external: false,
  },
  {
    key: 'whatsapp',
    label: 'WhatsApp',
    value: footerWhatsApp.label,
    href: footerWhatsApp.href,
    helper: 'Message us for fast replies during business hours',
    icon: MessageCircle,
    external: true,
  },
];

useHead(() =>
  seoHeadFromServer({
    title: `Contact | ${theme.brandName}`,
    description: `Contact ${theme.brandName} for order help, product questions, and support across India.`,
    canonical: '/contact',
  }),
);

async function submit() {
  error.value = '';
  success.value = '';
  submitting.value = true;

  try {
    const { data } = await api.post('/contact', { ...form });
    success.value = data.message || 'Thanks! Your message has been received.';
    form.name = '';
    form.email = '';
    form.message = '';
  } catch (err) {
    error.value =
      err.response?.data?.message ||
      Object.values(err.response?.data?.errors || {})[0]?.[0] ||
      'Unable to send your message.';
  } finally {
    submitting.value = false;
  }
}
</script>

<template>
  <div class="contact-page">
    <PageHero
      eyebrow="Support"
      title="Contact"
      lead="Reach the Ventures Mart team by WhatsApp, phone, or email—or send a message and we’ll get back to you."
    >
      <template #actions>
        <a
          class="button button--primary button--lg"
          :href="footerWhatsApp.href"
          target="_blank"
          rel="noopener noreferrer"
        >
          Chat on WhatsApp
        </a>
        <a
          class="button button--secondary button--lg"
          :href="`mailto:${footerContact.email}`"
        >
          Email us
        </a>
      </template>
    </PageHero>

    <section class="page-section contact-connect" aria-labelledby="contact-connect-title">
      <div class="contact-section-intro">
        <span class="eyebrow">Get in touch</span>
        <h2 id="contact-connect-title">Reach us or send a message</h2>
        <p>
          Use a direct channel for a quick reply, or share details in the form—we’ll follow up by
          email or WhatsApp.
        </p>
      </div>
      <div class="contact-connect__grid">
        <div class="contact-channels__list">
          <a
            v-for="channel in channels"
            :key="channel.key"
            class="contact-channel"
            :href="channel.href"
            :target="channel.external ? '_blank' : undefined"
            :rel="channel.external ? 'noopener noreferrer' : undefined"
          >
            <span class="contact-channel__icon" aria-hidden="true">
              <component :is="channel.icon" :size="20" />
            </span>
            <span class="contact-channel__copy">
              <span class="contact-channel__label">{{ channel.label }}</span>
              <strong>{{ channel.value }}</strong>
              <span class="contact-channel__helper">{{ channel.helper }}</span>
            </span>
          </a>
        </div>

        <div class="contact-form-panel">
          <h3 class="contact-form-panel__title">Send a message</h3>
          <form class="contact-form" @submit.prevent="submit">
            <p v-if="error" class="form-error">{{ error }}</p>
            <p v-if="success" class="form-success">{{ success }}</p>
            <FormField v-model="form.name" label="Name" required />
            <FormField v-model="form.email" label="Email" type="email" required />
            <FormField v-model="form.message" label="Message" type="textarea" :rows="5" required />
            <AppButton type="submit" size="lg" :disabled="submitting">
              {{ submitting ? 'Sending…' : 'Send message' }}
            </AppButton>
          </form>
          <p class="contact-form-note">
            We typically reply through email or WhatsApp with the details you need.
          </p>
        </div>
      </div>
    </section>
  </div>
</template>
