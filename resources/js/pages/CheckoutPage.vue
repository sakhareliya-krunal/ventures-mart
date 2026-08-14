<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useHead } from '@unhead/vue';
import { Banknote, CreditCard, MapPin, Pencil, Plus } from '@lucide/vue';
import OrderSummary from '@/components/cart/OrderSummary.vue';
import AppButton from '@/components/ui/AppButton.vue';
import FormField from '@/components/ui/FormField.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import PageHero from '@/components/ui/PageHero.vue';
import SearchableSelect from '@/components/ui/SearchableSelect.vue';
import api from '@/services/api';
import { phoneHref } from '@/utils/contactLinks';
import { unwrapData, formatCurrency } from '@/utils/format';
import {
  cityForDistrictChange,
  districtOptionsForState,
  indiaStateOptions,
  isDistrictInState,
} from '@/utils/indiaLocations';
import { friendlyApiError } from '@/utils/apiError';
import { useAuthStore } from '@/stores/auth';
import { useCartStore } from '@/stores/cart';
import { useThemeStore } from '@/stores/theme';
import { useUiStore } from '@/stores/ui';
import { seoHeadFromServer } from '@/utils/seoHead';

const theme = useThemeStore();
const auth = useAuthStore();
const cart = useCartStore();
const ui = useUiStore();
const router = useRouter();

const FIELD_KEYS = ['full_name', 'email', 'phone', 'address', 'city', 'district', 'state', 'postal_code'];

const FIELD_LABELS = {
  full_name: 'Full name',
  email: 'Email',
  phone: 'Phone',
  address: 'Address',
  city: 'City / Town',
  district: 'District',
  state: 'State',
  postal_code: 'Postal code',
};

const error = ref('');
const fieldErrors = reactive({
  full_name: '',
  email: '',
  phone: '',
  address: '',
  city: '',
  district: '',
  state: '',
  postal_code: '',
});
const submitting = ref(false);
const checkoutIdempotencyKey = ref('');
const paymentMethod = ref('razorpay');
const address = reactive({
  full_name: '',
  email: '',
  phone: '',
  address: '',
  city: '',
  district: '',
  state: '',
  postal_code: '',
});

const savedAddresses = ref([]);
const selectedAddressId = ref(null);
const showAddForm = ref(false);
const editingAddressId = ref(null);
const savingAddress = ref(false);
const addressesLoading = ref(Boolean(auth.user));
const addError = ref('');
const addFieldErrors = reactive({
  full_name: '',
  phone: '',
  address: '',
  city: '',
  district: '',
  state: '',
  postal_code: '',
});
const addForm = reactive({
  label: 'Home',
  full_name: '',
  phone: '',
  address: '',
  city: '',
  district: '',
  state: '',
  postal_code: '',
  is_default: false,
});

const ADD_ADDRESS_KEYS = ['full_name', 'phone', 'address', 'city', 'district', 'state', 'postal_code'];
let addressesRequestId = 0;
let bannerTimer = null;

const isLoggedIn = computed(() => Boolean(auth.user));
const showShippingFields = computed(
  () => !isLoggedIn.value || (!addressesLoading.value && savedAddresses.value.length === 0),
);
const checkoutDistrictOptions = computed(() => districtOptionsForState(address.state));
const savedAddressDistrictOptions = computed(() => districtOptionsForState(addForm.state));
const addressFormTitle = computed(() => (editingAddressId.value ? 'Edit address' : 'Add address'));
const addressFormSubmitLabel = computed(() => {
  if (savingAddress.value) return 'Saving…';
  return editingAddressId.value ? 'Save changes' : 'Save & use address';
});

const COD_FEE = 99;

const onlineTotal = computed(() => {
  const base = cart.totals || {};
  const subtotal = Number(base.subtotal || 0);
  const shipping = Number(base.shipping || 0);
  const tax = Number(base.tax || 0);
  return Math.round(Number(base.total ?? subtotal + shipping + tax) * 100) / 100;
});

const codTotal = computed(() => Math.round((onlineTotal.value + COD_FEE) * 100) / 100);

const displayTotals = computed(() => {
  const base = cart.totals || {};
  const subtotal = Number(base.subtotal || 0);
  const shipping = Number(base.shipping || 0);
  const tax = Number(base.tax || 0);
  const cgst = Number(base.cgst || 0);
  const sgst = Number(base.sgst || 0);
  const igst = Number(base.igst || 0);
  const codFee = paymentMethod.value === 'cod' ? COD_FEE : 0;

  return {
    ...base,
    subtotal,
    shipping,
    tax,
    cgst,
    sgst,
    igst,
    cod_fee: codFee,
    total: Math.round((onlineTotal.value + codFee) * 100) / 100,
  };
});

const submitLabel = computed(() => {
  if (submitting.value) {
    return paymentMethod.value === 'cod' ? 'Placing order…' : 'Processing…';
  }
  return paymentMethod.value === 'cod' ? 'Place order' : 'Pay Now';
});

useHead(() =>
  seoHeadFromServer({
    title: `Checkout | ${theme.brandName}`,
    description: `Secure checkout for toys and lunch boxes at ${theme.brandName}.`,
    canonical: '/checkout',
    robots: 'noindex,follow',
  }),
);

function clearBannerTimer() {
  if (bannerTimer != null) {
    clearTimeout(bannerTimer);
    bannerTimer = null;
  }
}

function clearFieldErrors() {
  for (const key of FIELD_KEYS) {
    fieldErrors[key] = '';
  }
}

function setBanner(message, { transient = false } = {}) {
  clearBannerTimer();
  error.value = message || '';
  if (transient && message) {
    bannerTimer = setTimeout(() => {
      error.value = '';
      bannerTimer = null;
    }, 5000);
  }
}

function isPaymentCancelledMessage(message) {
  return /payment cancelled/i.test(String(message || ''));
}

function validateAddress() {
  clearFieldErrors();
  let ok = true;

  for (const key of FIELD_KEYS) {
    if (!String(address[key] || '').trim()) {
      fieldErrors[key] = `${FIELD_LABELS[key]} is required.`;
      ok = false;
    }
  }

  const email = String(address.email || '').trim();
  if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    fieldErrors.email = 'Enter a valid email address.';
    ok = false;
  }

  return ok;
}

function applyApiFieldErrors(errors) {
  clearFieldErrors();
  let applied = false;

  for (const key of FIELD_KEYS) {
    const messages = errors?.[key];
    if (Array.isArray(messages) && messages[0]) {
      fieldErrors[key] = String(messages[0]);
      applied = true;
    }
  }

  return applied;
}

function clearAddFieldErrors() {
  for (const key of ADD_ADDRESS_KEYS) {
    addFieldErrors[key] = '';
  }
}

function validateAddAddressForm() {
  clearAddFieldErrors();
  let ok = true;

  for (const key of ADD_ADDRESS_KEYS) {
    if (!String(addForm[key] || '').trim()) {
      addFieldErrors[key] = `${FIELD_LABELS[key]} is required.`;
      ok = false;
    }
  }

  return ok;
}

function applySavedAddress(item) {
  if (!item) return;
  address.full_name = item.full_name || address.full_name || auth.user?.name || '';
  address.email = auth.user?.email || address.email;
  address.phone = item.phone || '';
  address.address = item.address || '';
  address.city = item.city || '';
  address.state = item.state || '';
  address.district = item.district || '';
  address.postal_code = item.postal_code || '';
  selectedAddressId.value = item.id;
  clearFieldErrors();
}

function isAddressComplete(item) {
  if (!item) return false;
  return ['full_name', 'phone', 'address', 'city', 'district', 'state', 'postal_code']
    .every((key) => String(item[key] || '').trim());
}

function ensureSelectedAddressEditable(item) {
  if (!item || isAddressComplete(item) || showAddForm.value) return;
  openEditForm(item);
}

function selectAddress(id) {
  const item = savedAddresses.value.find((entry) => entry.id === id);
  if (!item) return;
  applySavedAddress(item);
  showAddForm.value = false;
  editingAddressId.value = null;
  addError.value = '';
  ensureSelectedAddressEditable(item);
}

function resetAddForm() {
  editingAddressId.value = null;
  addForm.label = 'Home';
  addForm.full_name = auth.user?.name || address.full_name || '';
  addForm.phone = '';
  addForm.address = '';
  addForm.city = '';
  addForm.state = '';
  addForm.district = '';
  addForm.postal_code = '';
  addForm.is_default = true;
  addError.value = '';
  clearAddFieldErrors();
}

function openAddForm() {
  resetAddForm();
  showAddForm.value = true;
}

function openEditForm(item, event) {
  event?.preventDefault();
  event?.stopPropagation();
  if (!item) return;

  editingAddressId.value = item.id;
  addForm.label = item.label || 'Home';
  addForm.full_name = item.full_name || '';
  addForm.phone = item.phone || '';
  addForm.address = item.address || '';
  addForm.city = item.city || '';
  addForm.state = item.state || '';
  addForm.district = item.district || '';
  addForm.postal_code = item.postal_code || '';
  addForm.is_default = Boolean(item.is_default);
  addError.value = '';
  clearAddFieldErrors();
  showAddForm.value = true;
}

function cancelAddForm() {
  showAddForm.value = false;
  editingAddressId.value = null;
  addError.value = '';
  clearAddFieldErrors();
}

async function loadAddresses({ selectId = null } = {}) {
  const userId = auth.user?.id;
  if (!userId) {
    savedAddresses.value = [];
    selectedAddressId.value = null;
    addressesLoading.value = false;
    return;
  }

  const requestId = ++addressesRequestId;
  addressesLoading.value = true;
  savedAddresses.value = [];

  try {
    const { data } = await api.get('/addresses');
    if (requestId !== addressesRequestId || auth.user?.id !== userId) return;

    const list = unwrapData(data) || [];
    savedAddresses.value = list;

    const preferred =
      (selectId && list.find((item) => item.id === selectId)) ||
      list.find((item) => item.is_default) ||
      list[0] ||
      null;

    if (preferred) {
      applySavedAddress(preferred);
      ensureSelectedAddressEditable(preferred);
    } else {
      selectedAddressId.value = null;
      address.full_name = auth.user.name || address.full_name;
      address.email = auth.user.email || address.email;
    }
  } catch {
    if (requestId !== addressesRequestId || auth.user?.id !== userId) return;
    savedAddresses.value = [];
  } finally {
    if (requestId === addressesRequestId) {
      addressesLoading.value = false;
    }
  }
}

async function saveAddressForm() {
  if (savingAddress.value || !auth.user) return;

  addError.value = '';
  if (!validateAddAddressForm()) {
    addError.value = 'Complete every required address field.';
    return;
  }

  savingAddress.value = true;

  const payload = {
    label: addForm.label || 'Home',
    full_name: addForm.full_name,
    phone: addForm.phone,
    address: addForm.address,
    city: addForm.city,
    district: addForm.district,
    state: addForm.state,
    postal_code: addForm.postal_code,
    is_default: Boolean(addForm.is_default) || !editingAddressId.value || savedAddresses.value.length === 0,
  };

  try {
    let selectId = editingAddressId.value;

    if (editingAddressId.value) {
      const { data } = await api.patch(`/addresses/${editingAddressId.value}`, payload);
      const updated = unwrapData(data) || data.data;
      selectId = updated?.id || editingAddressId.value;
      ui.showToast('Address updated.', { type: 'success' });
    } else {
      const { data } = await api.post('/addresses', payload);
      const created = unwrapData(data) || data.data;
      selectId = created?.id || null;
      ui.showToast('Address saved.', { type: 'success' });
    }

    showAddForm.value = false;
    editingAddressId.value = null;
    await loadAddresses({ selectId });
  } catch (err) {
    addError.value = friendlyApiError(err, 'Unable to save address.');
  } finally {
    savingAddress.value = false;
  }
}

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
  (state, previousState) => {
    if (
      state !== previousState
      && address.district
      && !isDistrictInState(state, address.district)
    ) {
      address.district = '';
    }
    refreshTotalsForState();
  },
);

watch(
  () => addForm.state,
  (state, previousState) => {
    if (
      state !== previousState
      && addForm.district
      && !isDistrictInState(state, addForm.district)
    ) {
      addForm.district = '';
    }
  },
);

watch(
  () => address.district,
  (district, previousDistrict) => {
    address.city = cityForDistrictChange(address.city, district, previousDistrict);
  },
);

watch(
  () => addForm.district,
  (district, previousDistrict) => {
    addForm.city = cityForDistrictChange(addForm.city, district, previousDistrict);
  },
);

for (const key of FIELD_KEYS) {
  watch(
    () => address[key],
    () => {
      if (fieldErrors[key]) {
        fieldErrors[key] = '';
      }
    },
  );
}

for (const key of ADD_ADDRESS_KEYS) {
  watch(
    () => addForm[key],
    () => {
      if (addFieldErrors[key]) {
        addFieldErrors[key] = '';
      }
    },
  );
}

watch(
  () => auth.user?.id,
  (userId, previousUserId) => {
    if (userId === previousUserId) return;

    selectedAddressId.value = null;
    showAddForm.value = false;
    resetAddForm();

    if (userId) {
      address.full_name = auth.user?.name || '';
      address.email = auth.user?.email || '';
      loadAddresses();
    } else {
      addressesRequestId += 1;
      savedAddresses.value = [];
      addressesLoading.value = false;
    }
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
    await loadAddresses();
  }

  await refreshTotalsForState();
});

onUnmounted(() => {
  clearBannerTimer();
});

async function submit() {
  setBanner('');
  clearFieldErrors();

  if (!validateAddress()) {
    return;
  }

  submitting.value = true;
  checkoutIdempotencyKey.value ||= globalThis.crypto?.randomUUID?.()
    || `checkout-${Date.now()}-${Math.random().toString(36).slice(2)}`;
  const requestConfig = {
    headers: { 'Idempotency-Key': checkoutIdempotencyKey.value },
  };

  try {
    if (paymentMethod.value === 'cod') {
      const { data } = await api.post('/orders', {
        ...address,
        payment_method: 'cod',
      }, requestConfig);
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
    }, requestConfig);
    const order = unwrapData(data) || data.data;
    const razorpay = data.razorpay;

    if (!order?.id || !razorpay?.order_id) {
      throw new Error('Payment session missing. Please try again.');
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
    if (err?.response) {
      checkoutIdempotencyKey.value = '';
    }
    const isClientError = Boolean(err?.message) && !err.response;

    if (err?.response?.status === 422) {
      const errors = err.response.data?.errors || {};
      const applied = applyApiFieldErrors(errors);
      const cartMessage = Array.isArray(errors.cart) ? errors.cart[0] : '';
      setBanner(
        cartMessage
          || (applied ? '' : (err.response.data?.message || 'Please check the highlighted fields and try again.')),
      );
      if (cartMessage) {
        ui.showToast(cartMessage, { type: 'error' });
      }
      return;
    }

    const message = isClientError
      ? String(err.message)
      : friendlyApiError(err, 'Unable to complete checkout.');

    setBanner(message, { transient: isClientError });

    if (!isPaymentCancelledMessage(message)) {
      ui.showToast(message, { type: 'error' });
    }
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
        <form class="form-panel" @submit.prevent="submit" novalidate>
          <p v-if="error" class="form-error">{{ error }}</p>

          <div v-if="isLoggedIn" class="checkout-addresses">
            <div class="checkout-addresses__header">
              <h3 class="checkout-addresses__title">Delivery address</h3>
              <button type="button" class="checkout-addresses__add-btn" @click="openAddForm">
                <Plus :size="16" aria-hidden="true" />
                Add address
              </button>
            </div>

            <div v-if="addressesLoading" class="checkout-addresses__loading" aria-live="polite">
              <LoadingSpinner size="sm" label="Loading addresses…" />
            </div>
            <div
              v-else-if="savedAddresses.length"
              class="checkout-addresses__list"
              role="radiogroup"
              aria-label="Saved addresses"
            >
              <label
                v-for="item in savedAddresses"
                :key="item.id"
                class="checkout-addresses__option"
                :class="{ 'is-selected': selectedAddressId === item.id }"
              >
                <input
                  type="radio"
                  name="checkout_address"
                  :value="item.id"
                  :checked="selectedAddressId === item.id"
                  @change="selectAddress(item.id)"
                />
                <span class="checkout-addresses__icon" aria-hidden="true">
                  <MapPin :size="18" />
                </span>
                <span class="checkout-addresses__copy">
                  <span class="checkout-addresses__label-row">
                    <strong>{{ item.label || 'Address' }}</strong>
                    <span v-if="item.is_default" class="checkout-addresses__badge">Default</span>
                  </span>
                  <small>
                    {{ item.full_name }}
                    <template v-if="item.phone">
                      ·
                      <a :href="phoneHref(item.phone)" @click.stop>{{ item.phone }}</a>
                    </template>
                  </small>
                  <small>
                    {{ item.address }}, {{ item.city }}
                    <template v-if="item.district">, {{ item.district }}</template>,
                    {{ item.state }} {{ item.postal_code }}
                  </small>
                </span>
                <button
                  type="button"
                  class="checkout-addresses__edit"
                  aria-label="Edit address"
                  @click="openEditForm(item, $event)"
                >
                  <Pencil :size="16" aria-hidden="true" />
                </button>
              </label>
            </div>
            <p v-else class="checkout-addresses__empty">
              No saved addresses yet. Add one, or enter delivery details below.
            </p>

            <div v-if="showAddForm" class="checkout-addresses__add">
              <h4 class="checkout-addresses__add-title">{{ addressFormTitle }}</h4>
              <p v-if="addError" class="form-error">{{ addError }}</p>
              <FormField v-model="addForm.label" label="Label" placeholder="Home, Work…" />
              <FormField
                v-model="addForm.full_name"
                label="Full name"
                required
                :error="addFieldErrors.full_name"
              />
              <FormField
                v-model="addForm.phone"
                label="Phone"
                required
                :error="addFieldErrors.phone"
              />
              <FormField
                v-model="addForm.address"
                label="Address"
                required
                :error="addFieldErrors.address"
              />
              <div class="form-grid address-location-grid">
                <SearchableSelect
                  v-model="addForm.state"
                  label="State"
                  :options="indiaStateOptions"
                  placeholder="Select state"
                  search-placeholder="Search states…"
                  required
                  :error="addFieldErrors.state"
                />
                <SearchableSelect
                  v-model="addForm.district"
                  label="District"
                  :options="savedAddressDistrictOptions"
                  placeholder="Select district"
                  search-placeholder="Search districts…"
                  :disabled="!addForm.state"
                  required
                  :error="addFieldErrors.district"
                />
                <FormField
                  v-model="addForm.city"
                  label="City / Town"
                  required
                  :error="addFieldErrors.city"
                />
                <FormField
                  v-model="addForm.postal_code"
                  label="Postal code"
                  required
                  :error="addFieldErrors.postal_code"
                />
              </div>
              <label class="checkout-addresses__default">
                <input v-model="addForm.is_default" type="checkbox" />
                Set as default shipping address
              </label>
              <div class="checkout-addresses__add-actions">
                <AppButton type="button" :disabled="savingAddress" @click="saveAddressForm">
                  {{ addressFormSubmitLabel }}
                </AppButton>
                <AppButton type="button" variant="ghost" :disabled="savingAddress" @click="cancelAddForm">
                  Cancel
                </AppButton>
              </div>
            </div>
          </div>

          <template v-if="showShippingFields">
            <FormField
              v-model="address.full_name"
              label="Full name"
              required
              :error="fieldErrors.full_name"
            />
            <FormField
              v-model="address.email"
              label="Email"
              type="email"
              required
              :error="fieldErrors.email"
            />
            <FormField
              v-model="address.phone"
              label="Phone"
              required
              :error="fieldErrors.phone"
            />
            <FormField
              v-model="address.address"
              label="Address"
              required
              :error="fieldErrors.address"
            />
            <div class="form-grid address-location-grid">
              <SearchableSelect
                v-model="address.state"
                label="State"
                :options="indiaStateOptions"
                placeholder="Select state"
                search-placeholder="Search states…"
                required
                :error="fieldErrors.state"
              />
              <SearchableSelect
                v-model="address.district"
                label="District"
                :options="checkoutDistrictOptions"
                placeholder="Select district"
                search-placeholder="Search districts…"
                :disabled="!address.state"
                required
                :error="fieldErrors.district"
              />
              <FormField
                v-model="address.city"
                label="City / Town"
                required
                :error="fieldErrors.city"
              />
              <FormField
                v-model="address.postal_code"
                label="Postal code"
                required
                :error="fieldErrors.postal_code"
              />
            </div>
          </template>

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
              <span class="checkout-payment__price">
                <strong>{{ formatCurrency(onlineTotal) }}</strong>
                <small>No extra charge</small>
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
              <span class="checkout-payment__price">
                <strong>{{ formatCurrency(codTotal) }}</strong>
                <small>+{{ formatCurrency(COD_FEE) }} COD charge</small>
              </span>
            </label>
          </fieldset>

          <AppButton size="lg" type="submit" :disabled="submitting">
            {{ submitLabel }}
          </AppButton>
        </form>
        <OrderSummary :totals="displayTotals" />
      </div>
    </div>
  </section>
</template>
