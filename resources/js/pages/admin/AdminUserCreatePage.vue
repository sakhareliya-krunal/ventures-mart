<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import AppButton from '@/components/ui/AppButton.vue';
import FormField from '@/components/ui/FormField.vue';
import api from '@/services/api';

const router = useRouter();
const saving = ref(false);
const error = ref('');

const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
});

function goBack() {
  router.push({ name: 'admin-account' });
}

async function save() {
  if (saving.value) return;

  saving.value = true;
  error.value = '';

  try {
    await api.post('/admin/users', { ...form });
    await router.push({ name: 'admin-account', query: { notice: 'admin-created' } });
  } catch (err) {
    error.value =
      err.response?.data?.message ||
      Object.values(err.response?.data?.errors || {})[0]?.[0] ||
      'Unable to create admin.';
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <div class="admin-panel">
    <div class="admin-toolbar">
      <div>
        <h2>Create admin</h2>
        <p class="admin-muted">Add a new administrator who can access the admin panel.</p>
      </div>
      <AppButton type="button" variant="secondary" @click="goBack">Back</AppButton>
    </div>

    <p v-if="error" class="form-error">{{ error }}</p>

    <form novalidate class="admin-form" @submit.prevent="save">
      <FormField v-model="form.name" label="Name" required autocomplete="name" :disabled="saving" />
      <FormField
        v-model="form.email"
        label="Email"
        type="email"
        required
        autocomplete="email"
        :disabled="saving"
      />
      <FormField
        v-model="form.password"
        label="Password"
        type="password"
        required
        autocomplete="new-password"
        :disabled="saving"
      />
      <FormField
        v-model="form.password_confirmation"
        label="Confirm password"
        type="password"
        required
        autocomplete="new-password"
        :disabled="saving"
      />
      <div class="admin-form__actions">
        <AppButton type="submit" :loading="saving">
          Create admin
        </AppButton>
        <AppButton type="button" variant="ghost" :disabled="saving" @click="goBack">
          Cancel
        </AppButton>
      </div>
    </form>
  </div>
</template>
