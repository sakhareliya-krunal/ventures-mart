<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AdminFormGuard from '@/components/admin/AdminFormGuard.vue';
import AdminLoading from '@/components/admin/AdminLoading.vue';
import AdminPanel from '@/components/admin/AdminPanel.vue';
import AdminProductForm from '@/components/admin/AdminProductForm.vue';
import AppButton from '@/components/ui/AppButton.vue';
import api from '@/services/api';
import { unwrapData } from '@/utils/format';
import {
  apiErrorMessage,
  blankProductForm,
  buildProductPayload,
  categoryOptionsFromList,
  fillProductForm,
  validateProductForm,
} from '@/utils/adminProductForm';

const route = useRoute();
const router = useRouter();
const loading = ref(true);
const saving = ref(false);
const error = ref('');
const loadError = ref('');
const fieldErrors = ref({});
const categories = ref([]);
const form = reactive(blankProductForm());

const categoryOptions = computed(() => categoryOptionsFromList(categories.value));

function goBack() {
  router.push({ name: 'admin-products' });
}

async function load() {
  loading.value = true;
  loadError.value = '';
  try {
    const [{ data: productData }, { data: categoryData }] = await Promise.all([
      api.get(`/admin/products/${route.params.id}`),
      api.get('/admin/categories'),
    ]);
    categories.value = unwrapData(categoryData) || [];
    fillProductForm(form, unwrapData(productData) || {});
  } catch (err) {
    loadError.value = apiErrorMessage(err, 'Unable to load product.');
  } finally {
    loading.value = false;
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
    await api.put(
      `/admin/products/${route.params.id}`,
      buildProductPayload(form, { includeStock: false }),
    );
    await router.push({ name: 'admin-products', query: { notice: 'saved' } });
  } catch (err) {
    fieldErrors.value = err.response?.data?.errors || {};
    error.value = apiErrorMessage(err, 'Unable to save product.');
  } finally {
    saving.value = false;
  }
}

onMounted(load);
</script>

<template>
  <AdminFormGuard>
    <div>
      <div class="admin-toolbar">
        <AppButton type="button" variant="ghost" @click="goBack">← Back to products</AppButton>
      </div>

      <AdminLoading v-if="loading" page label="Loading product" />
      <AdminPanel v-else-if="loadError">
        <p class="form-error">{{ loadError }}</p>
        <AppButton type="button" variant="secondary" @click="load">Try again</AppButton>
        <AppButton type="button" variant="ghost" @click="goBack">Back to products</AppButton>
      </AdminPanel>
      <template v-else>
        <AdminPanel>
          <h2>Edit product</h2>
          <p class="admin-muted">Update catalog details, then save to return to the list.</p>
        </AdminPanel>
        <AdminProductForm
          :form="form"
          :category-options="categoryOptions"
          :field-errors="fieldErrors"
          :error="error"
          :saving="saving"
          editing
          @submit="save"
          @cancel="goBack"
        />
      </template>
    </div>
  </AdminFormGuard>
</template>
