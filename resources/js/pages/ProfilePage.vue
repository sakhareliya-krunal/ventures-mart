<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useHead } from '@unhead/vue';
import AppButton from '@/components/ui/AppButton.vue';
import FormField from '@/components/ui/FormField.vue';
import PageHero from '@/components/ui/PageHero.vue';
import api from '@/services/api';
import { unwrapData } from '@/utils/format';
import { useAuthStore } from '@/stores/auth';
import { useThemeStore } from '@/stores/theme';

const theme = useThemeStore();
const auth = useAuthStore();
const router = useRouter();

const accountError = ref('');
const accountSuccess = ref('');
const passwordError = ref('');
const passwordSuccess = ref('');
const addressError = ref('');
const addressSuccess = ref('');
const savingAccount = ref(false);
const savingPassword = ref(false);
const savingAddress = ref(false);
const addresses = ref([]);
const editingId = ref(null);

const account = reactive({
  name: '',
  email: '',
});

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
  state: '',
  postal_code: '',
  is_default: false,
});

useHead({
  title: () => `Profile | ${theme.brandName}`,
});

onMounted(async () => {
  if (!auth.user) {
    await auth.fetchUser();
  }

  account.name = auth.user?.name || '';
  account.email = auth.user?.email || '';
  await loadAddresses();
});

async function loadAddresses() {
  try {
    const { data } = await api.get('/addresses');
    addresses.value = unwrapData(data) || [];
  } catch {
    addresses.value = [];
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
  addressForm.postal_code = '';
  addressForm.is_default = addresses.value.length === 0;
}

function editAddress(item) {
  editingId.value = item.id;
  addressForm.label = item.label || 'Home';
  addressForm.full_name = item.full_name || '';
  addressForm.phone = item.phone || '';
  addressForm.address = item.address || '';
  addressForm.city = item.city || '';
  addressForm.state = item.state || '';
  addressForm.postal_code = item.postal_code || '';
  addressForm.is_default = Boolean(item.is_default);
  addressError.value = '';
  addressSuccess.value = '';
}

async function saveAccount() {
  accountError.value = '';
  accountSuccess.value = '';
  savingAccount.value = true;

  try {
    const { data } = await api.patch('/profile', { ...account });
    auth.user = unwrapData(data);
    accountSuccess.value = 'Profile updated.';
  } catch (err) {
    accountError.value =
      err.response?.data?.message ||
      Object.values(err.response?.data?.errors || {})[0]?.[0] ||
      'Unable to update profile.';
  } finally {
    savingAccount.value = false;
  }
}

async function savePassword() {
  passwordError.value = '';
  passwordSuccess.value = '';
  savingPassword.value = true;

  try {
    const { data } = await api.put('/profile/password', { ...passwordForm });
    passwordSuccess.value = data.message || 'Password updated.';
    passwordForm.current_password = '';
    passwordForm.password = '';
    passwordForm.password_confirmation = '';
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

async function logout() {
  await auth.logout();
  await router.push('/');
}
</script>

<template>
  <div class="profile-page">
    <PageHero
      eyebrow="Account"
      title="Your profile"
      lead="Update your details, manage saved addresses, and review orders."
      size="compact"
    >
      <template #actions>
        <AppButton to="/orders" variant="secondary" size="lg">View orders</AppButton>
        <AppButton variant="ghost" size="lg" @click="logout">Logout</AppButton>
      </template>
    </PageHero>

    <section class="page-section profile-sections">
      <div class="profile-panel">
        <h2>Account details</h2>
        <p v-if="accountError" class="form-error">{{ accountError }}</p>
        <p v-if="accountSuccess" class="form-success">{{ accountSuccess }}</p>
        <form class="contact-form" @submit.prevent="saveAccount">
          <FormField v-model="account.name" label="Name" required />
          <FormField v-model="account.email" label="Email" type="email" required />
          <AppButton type="submit" :disabled="savingAccount">
            {{ savingAccount ? 'Saving…' : 'Save profile' }}
          </AppButton>
        </form>
      </div>

      <div class="profile-panel">
        <h2>Change password</h2>
        <p v-if="passwordError" class="form-error">{{ passwordError }}</p>
        <p v-if="passwordSuccess" class="form-success">{{ passwordSuccess }}</p>
        <form class="contact-form" @submit.prevent="savePassword">
          <FormField
            v-model="passwordForm.current_password"
            label="Current password"
            type="password"
            required
          />
          <FormField v-model="passwordForm.password" label="New password" type="password" required />
          <FormField
            v-model="passwordForm.password_confirmation"
            label="Confirm password"
            type="password"
            required
          />
          <AppButton type="submit" :disabled="savingPassword">
            {{ savingPassword ? 'Updating…' : 'Update password' }}
          </AppButton>
        </form>
      </div>

      <div class="profile-panel profile-panel--wide">
        <h2>Saved addresses</h2>
        <p v-if="addressError" class="form-error">{{ addressError }}</p>
        <p v-if="addressSuccess" class="form-success">{{ addressSuccess }}</p>

        <ul v-if="addresses.length" class="address-list">
          <li v-for="item in addresses" :key="item.id" class="address-card">
            <div>
              <strong>{{ item.label }}</strong>
              <span v-if="item.is_default" class="address-badge">Default</span>
              <p>
                {{ item.full_name }} · {{ item.phone }}<br />
                {{ item.address }}, {{ item.city }}, {{ item.state }} {{ item.postal_code }}
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
        <p v-else class="muted">No saved addresses yet.</p>

        <h3>{{ editingId ? 'Edit address' : 'Add address' }}</h3>
        <form class="contact-form" @submit.prevent="saveAddress">
          <div class="form-grid">
            <FormField v-model="addressForm.label" label="Label" />
            <FormField v-model="addressForm.full_name" label="Full name" required />
            <FormField v-model="addressForm.phone" label="Phone" required />
          </div>
          <FormField v-model="addressForm.address" label="Address" required />
          <div class="form-grid">
            <FormField v-model="addressForm.city" label="City" required />
            <FormField v-model="addressForm.state" label="State" required />
            <FormField v-model="addressForm.postal_code" label="Postal code" required />
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
  </div>
</template>
