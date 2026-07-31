<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useHead } from '@unhead/vue';
import { Banknote, CreditCard } from '@lucide/vue';
import OrderSummary from '@/components/cart/OrderSummary.vue';
import AppButton from '@/components/ui/AppButton.vue';
import FormField from '@/components/ui/FormField.vue';
import PageHero from '@/components/ui/PageHero.vue';
import api from '@/services/api';
import { unwrapData } from '@/utils/format';
import { friendlyApiError } from '@/utils/apiError';
import { useAuthStore } from '@/stores/auth';
import { useCartStore } from '@/stores/cart';
import { useThemeStore } from '@/stores/theme';
import { useUiStore } from '@/stores/ui';

const theme = useThemeStore();
const auth = useAuthStore();
const cart = useCartStore();
const ui = useUiStore();
const router = useRouter();

const error = ref('');
const submitting = ref(false);
const paymentMethod = ref('razorpay');
const address = reactive({
  full_name: '',
  email: '',
  phone: '',
  address: '',
  city: '',
  state: '',
  postal_code: '',
});

const submitLabel = computed(() => {
  if (submitting.value) {
    return paymentMethod.value === 'cod' ? 'Placing order…' : 'Processing…';
  }
  return paymentMethod.value === 'cod' ? 'Place order' : 'Pay Now';
});

useHead({
  title: () => `Checkout | ${theme.brandName}`,
});

function loadRazorpayScript() {
  if (typeof window !== 'undefined' && window.Razorpay) {
    return Promise.resolve();
  }

  return new Promise((resolve, reject) => {
    const existing = document.querySelector('script[data-razorpay-checkout]');
    if (existing) {
      existing.addEventListener('load', () => resolve());
      existing.addEventListener('error', () => reject(new Error('Unable to load Razorpay.')));
      return;
    }

    const script = document.createElement('script');
    script.src = 'https://checkout.razorpay.com/v1/checkout.js';
    script.async = true;
    script.dataset.razorpayCheckout = '1';
    script.onload = () => resolve();
    script.onerror = () => reject(new Error('Unable to load Razorpay.'));
    document.head.appendChild(script);
  });
}

function openRazorpayCheckout(razorpay) {
  return new Promise((resolve, reject) => {
    if (!window.Razorpay) {
      reject(new Error('Razorpay is not available.'));
      return;
    }

    const options = {
      key: razorpay.key || import.meta.env.VITE_RAZORPAY_KEY_ID,
      amount: razorpay.amount,
      currency: razorpay.currency || 'INR',
      name: razorpay.name || theme.brandName,
      description: razorpay.description,
      order_id: razorpay.order_id,
      prefill: razorpay.prefill || {},
      theme: { color: '#0b2e8a' },
      handler(response) {
        resolve(response);
      },
      modal: {
        ondismiss() {
          reject(new Error('Payment cancelled. Your order is awaiting payment.'));
        },
      },
    };

    const checkout = new window.Razorpay(options);
    checkout.on('payment.failed', (response) => {
      const message =
        response?.error?.description ||
        response?.error?.reason ||
        'Payment failed. Please try again.';
      reject(new Error(message));
    });
    checkout.open();
  });
}

async function goToConfirmation(orderId) {
  await cart.fetch();
  await router.push({ name: 'order-confirmed', params: { id: orderId } });
}

async function refreshTotalsForState() {
  const state = String(address.state || '').trim();
  if (!state) {
    await cart.fetch();
    return;
  }
  await cart.fetch({ state });
}

watch(
  () => address.state,
  () => {
    refreshTotalsForState();
  },
);

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

  await refreshTotalsForState();
});

async function submit() {
  error.value = '';

  if (Object.values(address).some((value) => !String(value).trim())) {
    error.value = 'Complete every checkout field before placing your order.';
    return;
  }

  submitting.value = true;

  try {
    if (paymentMethod.value === 'cod') {
      const { data } = await api.post('/orders', {
        ...address,
        payment_method: 'cod',
      });
      const order = unwrapData(data) || data.data;

      if (!order?.id) {
        throw new Error('Unable to place COD order. Please try again.');
      }

      ui.showToast('Order placed. Pay cash on delivery.', { type: 'success' });
      await goToConfirmation(order.id);
      return;
    }

    await loadRazorpayScript();

    const { data } = await api.post('/orders', {
      ...address,
      payment_method: 'razorpay',
    });
    const order = unwrapData(data) || data.data;
    const razorpay = data.razorpay;

    if (!order?.id || !razorpay?.order_id) {
      throw new Error('Unable to start payment. Please try again.');
    }

    const payment = await openRazorpayCheckout(razorpay);

    await api.post(`/orders/${order.id}/payment/verify`, {
      razorpay_order_id: payment.razorpay_order_id,
      razorpay_payment_id: payment.razorpay_payment_id,
      razorpay_signature: payment.razorpay_signature,
    });

    ui.showToast('Payment successful. Your order is confirmed.', { type: 'success' });
    await goToConfirmation(order.id);
  } catch (err) {
    error.value =
      err?.message && !err.response
        ? String(err.message)
        : friendlyApiError(err, 'Unable to complete checkout.');
    ui.showToast(error.value, { type: 'error' });
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
      lead="Share delivery details, then pay online or choose cash on delivery."
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

          <fieldset class="checkout-payment">
            <legend class="checkout-payment__legend">Payment method</legend>
            <label
              class="checkout-payment__option"
              :class="{ 'is-selected': paymentMethod === 'razorpay' }"
            >
              <input v-model="paymentMethod" type="radio" name="payment_method" value="razorpay" />
              <span class="checkout-payment__icon" aria-hidden="true">
                <CreditCard :size="20" />
              </span>
              <span class="checkout-payment__copy">
                <strong>Pay online</strong>
                <small>UPI, cards, and net banking</small>
              </span>
            </label>
            <label
              class="checkout-payment__option"
              :class="{ 'is-selected': paymentMethod === 'cod' }"
            >
              <input v-model="paymentMethod" type="radio" name="payment_method" value="cod" />
              <span class="checkout-payment__icon" aria-hidden="true">
                <Banknote :size="20" />
              </span>
              <span class="checkout-payment__copy">
                <strong>Cash on Delivery</strong>
                <small>Pay when your order arrives</small>
              </span>
            </label>
          </fieldset>

          <AppButton size="lg" type="submit" :disabled="submitting">
            {{ submitLabel }}
          </AppButton>
        </form>
        <OrderSummary :totals="cart.totals" />
      </div>
    </div>
  </section>
</template>
