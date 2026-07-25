<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useHead } from '@unhead/vue';
import OrderSummary from '@/components/cart/OrderSummary.vue';
import AppButton from '@/components/ui/AppButton.vue';
import FormField from '@/components/ui/FormField.vue';
import PageHero from '@/components/ui/PageHero.vue';
import api from '@/services/api';
import { unwrapData } from '@/utils/format';
import { useAuthStore } from '@/stores/auth';
import { useCartStore } from '@/stores/cart';
import { useThemeStore } from '@/stores/theme';

const theme = useThemeStore();
const auth = useAuthStore();
const cart = useCartStore();
const router = useRouter();

const error = ref('');
const submitting = ref(false);
const address = reactive({
  full_name: '',
  email: '',
  phone: '',
  address: '',
  city: '',
  state: '',
  postal_code: '',
});

useHead({
  title: () => `Checkout | ${theme.brandName}`,
});

onMounted(async () => {
  await cart.fetch();

  if (!cart.items.length) {
    await router.replace('/cart');
    return;
  }

  if (auth.user) {
    address.full_name = auth.user.name || '';
    address.email = auth.user.email || '';

    try {
      const { data } = await api.get('/addresses');
      const list = unwrapData(data) || [];
      const preferred = list.find((item) => item.is_default) || list[0];
      if (preferred) {
        address.full_name = preferred.full_name || address.full_name;
        address.phone = preferred.phone || '';
        address.address = preferred.address || '';
        address.city = preferred.city || '';
        address.state = preferred.state || '';
        address.postal_code = preferred.postal_code || '';
      }
    } catch {
      // Keep name/email defaults when addresses cannot load.
    }
  }
});

async function submit() {
  error.value = '';

  if (Object.values(address).some((value) => !String(value).trim())) {
    error.value = 'Complete every checkout field before placing the order.';
    return;
  }

  submitting.value = true;

  try {
    await api.post('/orders', { ...address });
    await cart.fetch();
    await router.push('/orders');
  } catch (err) {
    error.value =
      err.response?.data?.message ||
      Object.values(err.response?.data?.errors || {})[0]?.[0] ||
      'Unable to place the order.';
  } finally {
    submitting.value = false;
  }
}
</script>

<template>
  <section class="checkout-page">
    <PageHero
      eyebrow="Checkout"
      title="Shipping details"
      lead="Share delivery details so we can pack and dispatch your order."
      size="compact"
    />
    <div class="page-section">
      <div class="checkout-layout">
        <form class="form-panel" @submit.prevent="submit">
          <p v-if="error" class="form-error">{{ error }}</p>
          <FormField v-model="address.full_name" label="Full name" required />
          <FormField v-model="address.email" label="Email" type="email" required />
          <FormField v-model="address.phone" label="Phone" required />
          <FormField v-model="address.address" label="Address" required />
          <div class="form-grid">
            <FormField v-model="address.city" label="City" required />
            <FormField v-model="address.state" label="State" required />
            <FormField v-model="address.postal_code" label="Postal code" required />
          </div>
          <AppButton size="lg" type="submit" :disabled="submitting">
            {{ submitting ? 'Placing order…' : 'Place order' }}
          </AppButton>
        </form>
        <OrderSummary :totals="cart.totals" />
      </div>
    </div>
  </section>
</template>
