<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useHead } from '@unhead/vue';
import { LogOut, Package } from '@lucide/vue';
import AppButton from '@/components/ui/AppButton.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import FormField from '@/components/ui/FormField.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import SearchableSelect from '@/components/ui/SearchableSelect.vue';
import api from '@/services/api';
import { emailHref, phoneHref } from '@/utils/contactLinks';
import { unwrapData } from '@/utils/format';
import {
  cityForDistrictChange,
  districtOptionsForState,
  indiaStateOptions,
  isDistrictInState,
} from '@/utils/indiaLocations';
import { useAuthStore } from '@/stores/auth';
import { useThemeStore } from '@/stores/theme';
import { seoHeadFromServer } from '@/utils/seoHead';

const theme = useThemeStore();
const auth = useAuthStore();
const router = useRouter();
const confirmLogoutOpen = ref(false);

const passwordError = ref('');
const passwordSuccess = ref('');
const addressError = ref('');
const addressSuccess = ref('');
const savingPassword = ref(false);
const savingAddress = ref(false);
const addresses = ref([]);
const addressesLoading = ref(true);
const editingId = ref(null);
let addressesRequestId = 0;

const ADDRESS_FIELD_KEYS = ['full_name', 'phone', 'address', 'city', 'district', 'state', 'postal_code'];
const ADDRESS_FIELD_LABELS = {
  full_name: 'Full name',
  phone: 'Phone',
  address: 'Address',
  city: 'City / Town',
  district: 'District',
  state: 'State',
  postal_code: 'Postal code',
};

const passwordForm = reactive({
  current_password: '',
  password: '',
  password_confirmation: '',
});

const addressForm = reactive({
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

const addressFieldErrors = reactive({
  full_name: '',
  phone: '',
  address: '',
  city: '',
  district: '',
  state: '',
  postal_code: '',
});

const displayName = computed(() => auth.user?.name || 'Account');
const displayEmail = computed(() => auth.user?.email || '');
const avatarUrl = computed(() => auth.user?.avatar || '');
const hasPassword = computed(() => Boolean(auth.user?.has_password));
const districtOptions = computed(() => districtOptionsForState(addressForm.state));
const avatarInitial = computed(() => {
  const name = displayName.value.trim();
  return name ? name.charAt(0).toUpperCase() : '?';
});

useHead(() =>
  seoHeadFromServer({
    title: `Profile | ${theme.brandName}`,
    description: `Manage your ${theme.brandName} account, addresses, and password.`,
    canonical: '/profile',
    robots: 'noindex,follow',
  }),
);

onMounted(async () => {
  if (!auth.user) {
    await auth.fetchUser();
  }
  await loadAddresses();
});

function clearAddressFieldErrors() {
  for (const key of ADDRESS_FIELD_KEYS) {
    addressFieldErrors[key] = '';
  }
}

function validateAddressForm() {
  clearAddressFieldErrors();
  let ok = true;

  for (const key of ADDRESS_FIELD_KEYS) {
    if (!String(addressForm[key] || '').trim()) {
      addressFieldErrors[key] = `${ADDRESS_FIELD_LABELS[key]} is required.`;
      ok = false;
    }
  }

  return ok;
}

async function loadAddresses() {
  const userId = auth.user?.id;
  if (!userId) {
    addresses.value = [];
    addressesLoading.value = false;
    return;
  }

  const requestId = ++addressesRequestId;
  addressesLoading.value = true;
  addresses.value = [];

  try {
    const { data } = await api.get('/addresses');
    if (requestId !== addressesRequestId || auth.user?.id !== userId) return;
    addresses.value = unwrapData(data) || [];
  } catch {
    if (requestId !== addressesRequestId || auth.user?.id !== userId) return;
    addresses.value = [];
  } finally {
    if (requestId === addressesRequestId) {
      addressesLoading.value = false;
    }
  }
}

function resetAddressForm() {
  editingId.value = null;
  addressForm.label = 'Home';
  addressForm.full_name = auth.user?.name || '';
  addressForm.phone = '';
  addressForm.address = '';
  addressForm.city = '';
  addressForm.state = '';
  addressForm.district = '';
  addressForm.postal_code = '';
  addressForm.is_default = addresses.value.length === 0;
  clearAddressFieldErrors();
}

function editAddress(item) {
  editingId.value = item.id;
  addressForm.label = item.label || 'Home';
  addressForm.full_name = item.full_name || '';
  addressForm.phone = item.phone || '';
  addressForm.address = item.address || '';
  addressForm.city = item.city || '';
  addressForm.state = item.state || '';
  addressForm.district = item.district || '';
  addressForm.postal_code = item.postal_code || '';
  addressForm.is_default = Boolean(item.is_default);
  addressError.value = '';
  addressSuccess.value = '';
  clearAddressFieldErrors();
}

async function savePassword() {
  passwordError.value = '';
  passwordSuccess.value = '';
  savingPassword.value = true;

  try {
    const payload = hasPassword.value
      ? { ...passwordForm }
      : {
          password: passwordForm.password,
          password_confirmation: passwordForm.password_confirmation,
        };
    const { data } = await api.put('/profile/password', payload);
    passwordSuccess.value = data.message || (hasPassword.value ? 'Password updated.' : 'Password set.');
    passwordForm.current_password = '';
    passwordForm.password = '';
    passwordForm.password_confirmation = '';
    if (data.user) {
      auth.user = unwrapData(data.user) ?? data.user;
    } else {
      await auth.fetchUser();
    }
  } catch (err) {
    passwordError.value =
      err.response?.data?.message ||
      Object.values(err.response?.data?.errors || {})[0]?.[0] ||
      'Unable to update password.';
  } finally {
    savingPassword.value = false;
  }
}

async function saveAddress() {
  addressError.value = '';
  addressSuccess.value = '';

  if (!validateAddressForm()) {
    addressError.value = 'Complete every required address field.';
    return;
  }

  savingAddress.value = true;

  try {
    if (editingId.value) {
      await api.patch(`/addresses/${editingId.value}`, { ...addressForm });
      addressSuccess.value = 'Address updated.';
    } else {
      await api.post('/addresses', { ...addressForm });
      addressSuccess.value = 'Address saved.';
    }
    await loadAddresses();
    resetAddressForm();
  } catch (err) {
    addressError.value =
      err.response?.data?.message ||
      Object.values(err.response?.data?.errors || {})[0]?.[0] ||
      'Unable to save address.';
  } finally {
    savingAddress.value = false;
  }
}

watch(
  () => addressForm.state,
  (state, previousState) => {
    if (
      state !== previousState
      && addressForm.district
      && !isDistrictInState(state, addressForm.district)
    ) {
      addressForm.district = '';
    }
  },
);

watch(
  () => addressForm.district,
  (district, previousDistrict) => {
    addressForm.city = cityForDistrictChange(addressForm.city, district, previousDistrict);
  },
);

watch(
  () => auth.user?.id,
  (userId, previousUserId) => {
    if (userId === previousUserId) return;
    resetAddressForm();
    if (userId) {
      loadAddresses();
    } else {
      addressesRequestId += 1;
      addresses.value = [];
      addressesLoading.value = false;
    }
  },
);

for (const key of ADDRESS_FIELD_KEYS) {
  watch(
    () => addressForm[key],
    () => {
      if (addressFieldErrors[key]) {
        addressFieldErrors[key] = '';
      }
    },
  );
}

async function setDefaultAddress(id) {
  await api.post(`/addresses/${id}/default`);
  await loadAddresses();
}

async function deleteAddress(id) {
  await api.delete(`/addresses/${id}`);
  if (editingId.value === id) {
    resetAddressForm();
  }
  await loadAddresses();
}

async function requestLogout() {
  if (auth.loggingOut) return;
  confirmLogoutOpen.value = true;
}

async function logout() {
  if (auth.loggingOut) return;
  await auth.logout();
  confirmLogoutOpen.value = false;
  await router.push('/');
}
</script>

<template>
  <div class="profile-page">
    <section class="profile-identity">
      <div class="profile-identity__glow" aria-hidden="true" />
      <div class="profile-identity__inner page-section">
        <div class="profile-identity__main">
          <div class="profile-avatar">
            <img
              v-if="avatarUrl"
              :src="avatarUrl"
              :alt="displayName"
              class="profile-avatar__image"
              referrerpolicy="no-referrer"
            />
            <span v-else class="profile-avatar__initial" aria-hidden="true">{{ avatarInitial }}</span>
          </div>
          <div class="profile-identity__copy">
            <p class="profile-identity__eyebrow">Account</p>
            <h1>{{ displayName }}</h1>
            <p class="profile-identity__email">
              <a v-if="displayEmail" :href="emailHref(displayEmail)">{{ displayEmail }}</a>
            </p>
            <p class="profile-identity__note">
              Name and email are managed by your account and can’t be edited here.
            </p>
          </div>
        </div>
        <div class="profile-identity__actions">
          <AppButton to="/orders" variant="primary" size="lg">
            <Package :size="18" />
            View orders
          </AppButton>
          <AppButton
            variant="secondary"
            size="lg"
            :disabled="auth.loggingOut"
            @click="requestLogout"
          >
            <LogOut :size="18" />
            Logout
          </AppButton>
        </div>
      </div>
    </section>

    <section class="page-section profile-body">
      <div class="profile-block">
        <header class="profile-block__header">
          <h2>{{ hasPassword ? 'Password' : 'Set a password' }}</h2>
          <p>
            {{
              hasPassword
                ? 'Keep your account secure with a strong password.'
                : 'Optional. Add a password so you can also sign in with email.'
            }}
          </p>
        </header>
        <p v-if="passwordError" class="form-error">{{ passwordError }}</p>
        <p v-if="passwordSuccess" class="form-success">{{ passwordSuccess }}</p>
        <form class="profile-form" @submit.prevent="savePassword">
          <FormField
            v-if="hasPassword"
            v-model="passwordForm.current_password"
            label="Current password"
            type="password"
            required
            autocomplete="current-password"
          />
          <FormField
            v-model="passwordForm.password"
            :label="hasPassword ? 'New password' : 'Password'"
            type="password"
            required
            autocomplete="new-password"
          />
          <FormField
            v-model="passwordForm.password_confirmation"
            label="Confirm password"
            type="password"
            required
            autocomplete="new-password"
          />
          <AppButton type="submit" :disabled="savingPassword">
            {{
              savingPassword
                ? hasPassword
                  ? 'Updating…'
                  : 'Saving…'
                : hasPassword
                  ? 'Update password'
                  : 'Set password'
            }}
          </AppButton>
        </form>
      </div>

      <div class="profile-block">
        <header class="profile-block__header">
          <h2>Saved addresses</h2>
          <p>Shipping destinations used at checkout.</p>
        </header>
        <p v-if="addressError" class="form-error">{{ addressError }}</p>
        <p v-if="addressSuccess" class="form-success">{{ addressSuccess }}</p>

        <LoadingSpinner v-if="addressesLoading" size="sm" label="Loading addresses…" />
        <ul v-else-if="addresses.length" class="address-list">
          <li v-for="item in addresses" :key="item.id" class="address-card">
            <div class="address-card__body">
              <div class="address-card__title">
                <strong>{{ item.label }}</strong>
                <span v-if="item.is_default" class="address-badge">Default</span>
              </div>
              <p>
                {{ item.full_name }}
                <template v-if="item.phone">
                  · <a :href="phoneHref(item.phone)">{{ item.phone }}</a>
                </template><br />
                {{ item.address }}, {{ item.city }}
                <template v-if="item.district">, {{ item.district }}</template>,
                {{ item.state }} {{ item.postal_code }}
              </p>
            </div>
            <div class="address-card__actions">
              <AppButton type="button" variant="secondary" size="sm" @click="editAddress(item)">
                Edit
              </AppButton>
              <AppButton
                v-if="!item.is_default"
                type="button"
                variant="ghost"
                size="sm"
                @click="setDefaultAddress(item.id)"
              >
                Make default
              </AppButton>
              <AppButton type="button" variant="ghost" size="sm" @click="deleteAddress(item.id)">
                Delete
              </AppButton>
            </div>
          </li>
        </ul>
        <p v-else class="profile-empty">No saved addresses yet.</p>

        <h3 class="profile-block__subheader">{{ editingId ? 'Edit address' : 'Add address' }}</h3>
        <form class="profile-form" novalidate @submit.prevent="saveAddress">
          <div class="form-grid">
            <FormField v-model="addressForm.label" label="Label" />
            <FormField
              v-model="addressForm.full_name"
              label="Full name"
              required
              :error="addressFieldErrors.full_name"
            />
            <FormField
              v-model="addressForm.phone"
              label="Phone"
              required
              :error="addressFieldErrors.phone"
            />
          </div>
          <FormField
            v-model="addressForm.address"
            label="Address"
            required
            :error="addressFieldErrors.address"
          />
          <div class="form-grid address-location-grid">
            <SearchableSelect
              v-model="addressForm.state"
              label="State"
              :options="indiaStateOptions"
              placeholder="Select state"
              search-placeholder="Search states…"
              required
              :error="addressFieldErrors.state"
            />
            <SearchableSelect
              v-model="addressForm.district"
              label="District"
              :options="districtOptions"
              placeholder="Select district"
              search-placeholder="Search districts…"
              :disabled="!addressForm.state"
              required
              :error="addressFieldErrors.district"
            />
            <FormField
              v-model="addressForm.city"
              label="City / Town"
              required
              :error="addressFieldErrors.city"
            />
            <FormField
              v-model="addressForm.postal_code"
              label="Postal code"
              required
              :error="addressFieldErrors.postal_code"
            />
          </div>
          <label class="checkbox-row">
            <input v-model="addressForm.is_default" type="checkbox" />
            Set as default shipping address
          </label>
          <div class="profile-actions">
            <AppButton type="submit" :disabled="savingAddress">
              {{ savingAddress ? 'Saving…' : editingId ? 'Update address' : 'Save address' }}
            </AppButton>
            <AppButton v-if="editingId" type="button" variant="ghost" @click="resetAddressForm">
              Cancel
            </AppButton>
          </div>
        </form>
      </div>
    </section>

    <ConfirmDialog
      v-model:open="confirmLogoutOpen"
      title="Log out?"
      message="You will leave your account and need to sign in again."
      confirm-label="Log out"
      busy-label="Signing out…"
      :busy="auth.loggingOut"
      :close-on-confirm="false"
      danger
      @confirm="logout"
    />
  </div>
</template>
