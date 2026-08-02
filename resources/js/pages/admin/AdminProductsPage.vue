<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AdminSearchField from '@/components/admin/AdminSearchField.vue';
import AppButton from '@/components/ui/AppButton.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import api from '@/services/api';
import { formatCurrency, unwrapData } from '@/utils/format';
import { apiErrorMessage } from '@/utils/adminProductForm';
import { isNetworkOrTimeoutError } from '@/utils/apiError';

const route = useRoute();
const router = useRouter();
const loading = ref(true);
const products = ref([]);
const search = ref('');
const confirmOpen = ref(false);
const pendingDeleteId = ref(null);
const deleting = ref(false);
const listError = ref('');
const successMessage = ref('');

let successTimer = null;
let networkRetryTimer = null;

function flashSuccess(message) {
  successMessage.value = message;
  if (successTimer) clearTimeout(successTimer);
  successTimer = setTimeout(() => {
    successMessage.value = '';
  }, 3500);
}

function consumeNotice() {
  const notice = route.query.notice;
  if (!notice) return;

  if (notice === 'created') flashSuccess('Product created.');
  else if (notice === 'saved') flashSuccess('Product saved.');

  const query = { ...route.query };
  delete query.notice;
  router.replace({ query });
}

function openCreate() {
  router.push({ name: 'admin-product-create' });
}

function openEdit(product) {
  router.push({ name: 'admin-product-edit', params: { id: product.id } });
}

async function load({ silent = false } = {}) {
  if (!silent) loading.value = true;
  listError.value = '';
  let holdLoader = false;
  try {
    const { data } = await api.get('/admin/products', {
      params: { search: search.value || undefined },
    });
    products.value = unwrapData(data) || [];
  } catch (err) {
    if (isNetworkOrTimeoutError(err)) {
      holdLoader = !silent;
      if (networkRetryTimer) clearTimeout(networkRetryTimer);
      networkRetryTimer = setTimeout(() => load({ silent }), 1500);
      return;
    }
    products.value = [];
    listError.value = apiErrorMessage(err, 'Unable to load products.');
  } finally {
    if (!silent && !holdLoader) loading.value = false;
  }
}

function requestRemove(id) {
  listError.value = '';
  pendingDeleteId.value = id;
  confirmOpen.value = true;
}

async function remove() {
  if (!pendingDeleteId.value || deleting.value) return;
  const id = pendingDeleteId.value;
  deleting.value = true;
  try {
    await api.delete(`/admin/products/${id}`);
    pendingDeleteId.value = null;
    confirmOpen.value = false;
    flashSuccess('Product deleted.');
    await load({ silent: true });
  } catch (err) {
    listError.value = apiErrorMessage(err, 'Unable to delete product.');
    pendingDeleteId.value = null;
  } finally {
    deleting.value = false;
  }
}

onMounted(() => {
  consumeNotice();
  load();
});

watch(search, load);

onBeforeUnmount(() => {
  if (successTimer) clearTimeout(successTimer);
  if (networkRetryTimer) clearTimeout(networkRetryTimer);
});
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

      <p v-if="successMessage" class="form-success">{{ successMessage }}</p>
      <p v-if="listError" class="form-error">{{ listError }}</p>
      <LoadingSpinner v-if="loading" page label="Loading products" />
      <div v-else class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>SKU</th>
              <th>Price</th>
              <th>Stock</th>
              <th>Status</th>
              <th />
            </tr>
          </thead>
          <tbody>
            <tr v-for="product in products" :key="product.id">
              <td data-label="Name">
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
              <td data-label="SKU">{{ product.sku || '—' }}</td>
              <td data-label="Price">{{ formatCurrency(product.price) }}</td>
              <td data-label="Stock">{{ product.stock }}</td>
              <td data-label="Status">
                <span
                  class="admin-badge"
                  :class="product.is_active === false ? 'admin-badge--warn' : 'admin-badge--ok'"
                >
                  {{ product.is_active === false ? 'Hidden' : 'Active' }}
                </span>
              </td>
              <td data-label="Actions">
                <div class="admin-actions">
                  <AppButton type="button" variant="secondary" size="sm" @click="openEdit(product)">
                    Edit
                  </AppButton>
                  <AppButton type="button" variant="danger" size="sm" @click="requestRemove(product.id)">
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

    <ConfirmDialog
      v-model:open="confirmOpen"
      title="Delete product?"
      message="This product will be permanently removed from the catalog."
      confirm-label="Delete"
      busy-label="Deleting…"
      :busy="deleting"
      :close-on-confirm="false"
      danger
      @confirm="remove"
    />
  </div>
</template>
