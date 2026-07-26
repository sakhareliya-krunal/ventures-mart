<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import AdminProductForm from '@/components/admin/AdminProductForm.vue';
import AppButton from '@/components/ui/AppButton.vue';
import api from '@/services/api';
import { unwrapData } from '@/utils/format';
import {
  apiErrorMessage,
  blankProductForm,
  buildProductPayload,
  categoryOptionsFromList,
  validateProductForm,
} from '@/utils/adminProductForm';

const router = useRouter();
const saving = ref(false);
const error = ref('');
const fieldErrors = ref({});
const categories = ref([]);
const form = reactive(blankProductForm());

const categoryOptions = computed(() => categoryOptionsFromList(categories.value));

function goBack() {
  router.push({ name: 'admin-products' });
}

async function loadCategories() {
  try {
    const { data } = await api.get('/admin/categories');
    categories.value = unwrapData(data) || [];
  } catch (err) {
    error.value = apiErrorMessage(err, 'Unable to load categories.');
  }
}

async function save() {
  if (saving.value) return;

  const errors = validateProductForm(form);
  fieldErrors.value = errors;
  if (Object.keys(errors).length) {
    error.value = Object.values(errors)[0][0];
    return;
  }

  saving.value = true;
  error.value = '';
  fieldErrors.value = {};

  try {
    await api.post('/admin/products', buildProductPayload(form));
    await router.push({ name: 'admin-products', query: { notice: 'created' } });
  } catch (err) {
    fieldErrors.value = err.response?.data?.errors || {};
    error.value = apiErrorMessage(err, 'Unable to save product.');
  } finally {
    saving.value = false;
  }
}

onMounted(loadCategories);
</script>

<template>
  <div>
    <div class="admin-toolbar">
      <AppButton type="button" variant="ghost" @click="goBack">← Back to products</AppButton>
    </div>
    <div class="admin-panel">
      <h2>New product</h2>
      <p class="admin-muted">Add a catalog product with images, pricing, and stock.</p>
    </div>
    <AdminProductForm
      :form="form"
      :category-options="categoryOptions"
      :field-errors="fieldErrors"
      :error="error"
      :saving="saving"
      @submit="save"
      @cancel="goBack"
    />
  </div>
</template>
