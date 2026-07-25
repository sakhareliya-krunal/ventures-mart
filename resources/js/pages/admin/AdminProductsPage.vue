<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import AdminProductForm from '@/components/admin/AdminProductForm.vue';
import AdminSearchField from '@/components/admin/AdminSearchField.vue';
import AppButton from '@/components/ui/AppButton.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import api from '@/services/api';
import { formatCurrency, unwrapData } from '@/utils/format';

const loading = ref(true);
const saving = ref(false);
const error = ref('');
const fieldErrors = ref({});
const products = ref([]);
const categories = ref([]);
const search = ref('');
const editingId = ref(null);
const showForm = ref(false);
const confirmOpen = ref(false);
const pendingDeleteId = ref(null);
const listError = ref('');

const form = reactive({
  name: '',
  slug: '',
  sku: '',
  category_id: '',
  price: 0,
  compare_at_price: '',
  stock: 0,
  images: [],
  description: '',
  badge: '',
});

const categoryOptions = computed(() =>
  categories.value.map((category) => ({
    value: category.id,
    label: category.name,
  })),
);

const blankForm = () => ({
  name: '',
  slug: '',
  sku: '',
  category_id: '',
  price: 0,
  compare_at_price: '',
  stock: 0,
  images: [],
  description: '',
  badge: '',
});

function clearFormState() {
  editingId.value = null;
  error.value = '';
  fieldErrors.value = {};
  Object.assign(form, blankForm());
}

function closeForm() {
  showForm.value = false;
  clearFormState();
}

function openCreate() {
  clearFormState();
  showForm.value = true;
}

function edit(product) {
  clearFormState();
  editingId.value = product.id;
  const gallery = Array.isArray(product.gallery) ? product.gallery.filter(Boolean) : [];
  const images = gallery.length ? gallery : product.image ? [product.image] : [];
  Object.assign(form, {
    name: product.name || '',
    slug: product.slug || '',
    sku: product.sku || '',
    category_id: product.category_id || '',
    price: product.price || 0,
    compare_at_price: product.compare_at_price ?? '',
    stock: product.stock || 0,
    images,
    description: product.description || '',
    badge: product.badge || '',
  });
  showForm.value = true;
}

async function load() {
  loading.value = true;
  listError.value = '';
  try {
    const [{ data: productData }, { data: categoryData }] = await Promise.all([
      api.get('/admin/products', { params: { search: search.value || undefined } }),
      api.get('/admin/categories'),
    ]);
    products.value = unwrapData(productData) || [];
    categories.value = unwrapData(categoryData) || [];
  } catch {
    listError.value = 'Unable to load products.';
  } finally {
    loading.value = false;
  }
}

async function save() {
  saving.value = true;
  error.value = '';
  fieldErrors.value = {};

  const images = (form.images || []).filter(Boolean);
  if (!images.length) {
    fieldErrors.value = { image: ['At least one product image is required.'] };
    error.value = 'At least one product image is required.';
    saving.value = false;
    return;
  }

  const payload = {
    name: form.name,
    slug: form.slug || null,
    sku: form.sku,
    category_id: form.category_id || null,
    price: form.price,
    compare_at_price: form.compare_at_price === '' ? null : form.compare_at_price,
    stock: form.stock,
    image: images[0],
    hover_image: images[1] || null,
    gallery: images.slice(1),
    description: form.description || '',
    badge: form.badge || null,
  };

  try {
    if (editingId.value) {
      await api.put(`/admin/products/${editingId.value}`, payload);
    } else {
      await api.post('/admin/products', payload);
    }
    closeForm();
    await load();
  } catch (err) {
    fieldErrors.value = err.response?.data?.errors || {};
    error.value =
      err.response?.data?.message ||
      Object.values(fieldErrors.value)[0]?.[0] ||
      'Unable to save product.';
  } finally {
    saving.value = false;
  }
}

function requestRemove(id) {
  listError.value = '';
  pendingDeleteId.value = id;
  confirmOpen.value = true;
}

async function remove() {
  if (!pendingDeleteId.value) return;
  const id = pendingDeleteId.value;
  try {
    await api.delete(`/admin/products/${id}`);
    pendingDeleteId.value = null;
    await load();
  } catch (err) {
    listError.value =
      err.response?.data?.message ||
      Object.values(err.response?.data?.errors || {})[0]?.[0] ||
      'Unable to delete product.';
    pendingDeleteId.value = null;
  }
}

onMounted(load);
watch(search, load);
</script>

<template>
  <div>
    <div class="admin-panel">
      <div class="admin-toolbar">
        <h2>Products</h2>
        <div class="admin-toolbar__filters">
          <AdminSearchField
            v-model="search"
            placeholder="Search products…"
            aria-label="Search products"
          />
          <AppButton type="button" @click="openCreate">Add product</AppButton>
        </div>
      </div>

      <p v-if="listError" class="form-error">{{ listError }}</p>
      <div v-if="loading" class="admin-muted">Loading…</div>
      <div v-else class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>SKU</th>
              <th>Price</th>
              <th>Stock</th>
              <th />
            </tr>
          </thead>
          <tbody>
            <tr v-for="product in products" :key="product.id">
              <td>
                <div class="admin-product-cell">
                  <img
                    v-if="product.image"
                    class="admin-product-cell__thumb"
                    :src="product.image"
                    :alt="product.name"
                  />
                  <div>
                    <strong>{{ product.name }}</strong>
                    <div class="admin-muted">{{ product.category_name || product.category }}</div>
                  </div>
                </div>
              </td>
              <td>{{ product.sku || '—' }}</td>
              <td>{{ formatCurrency(product.price) }}</td>
              <td>{{ product.stock }}</td>
              <td>
                <div class="admin-actions">
                  <AppButton type="button" variant="secondary" size="sm" @click="edit(product)">
                    Edit
                  </AppButton>
                  <AppButton type="button" variant="ghost" size="sm" @click="requestRemove(product.id)">
                    Delete
                  </AppButton>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
        <p v-if="!products.length" class="admin-empty">No products found.</p>
      </div>
    </div>

    <AdminProductForm
      v-model:open="showForm"
      :editing="Boolean(editingId)"
      :form="form"
      :category-options="categoryOptions"
      :field-errors="fieldErrors"
      :error="error"
      :saving="saving"
      @submit="save"
      @cancel="closeForm"
    />

    <ConfirmDialog
      v-model:open="confirmOpen"
      title="Delete product?"
      message="This product will be permanently removed from the catalog."
      confirm-label="Delete"
      danger
      @confirm="remove"
    />
  </div>
</template>
