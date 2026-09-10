<script setup>
import AdminDataList from '@/components/admin/AdminDataList.vue';
import AdminPagination from '@/components/admin/AdminPagination.vue';
import { useAdminList } from '@/composables/useAdminList';
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
const { rows: products, loading, refreshing, error: fetchError, search, filters, meta, filtered, load, go, reset } = useAdminList('/admin/products', {});
const confirmOpen = ref(false);
const pendingDeleteId = ref(null);
const deleting = ref(false);
const listError = ref('');
const successMessage = ref('');

let successTimer = null;

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
    flashSuccess('Product deleted from the storefront.');
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
});


onBeforeUnmount(() => {
  if (successTimer) clearTimeout(successTimer);
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
      <AdminDataList :rows="products" :loading="loading" :refreshing="refreshing" :error="fetchError" :searching="filtered" @retry="load" @reset="reset"><div class="admin-table-wrap">
        <table class="admin-table admin-products-table">
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
                    width="56"
                    height="56"
                    loading="lazy"
                    decoding="async"
                  />
                  <div class="admin-product-cell__copy">
                    <strong>{{ product.name }}</strong>
                    <div class="admin-muted">{{ product.category_name || product.category }}</div>
                  </div>
                </div>
              </td>
              <td data-label="SKU">{{ product.sku || '—' }}</td>
              <td data-label="Price">{{ formatCurrency(product.price) }}</td>
              <td data-label="Stock">
                <RouterLink
                  :to="{ name: 'admin-inventory', query: { search: product.sku || product.name } }"
                  class="admin-products-table__stock-link"
                >
                  {{ product.stock }}
                </RouterLink>
              </td>
              <td data-label="Status">
                <span
                  class="admin-badge admin-products-table__status"
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
      </div></AdminDataList>
      <AdminPagination :page="meta.current_page" :last-page="meta.last_page" :total="meta.total" :from="meta.from || 0" :to="meta.to || 0" @page="go" />
    </div>

    <ConfirmDialog
      v-model:open="confirmOpen"
      title="Delete product?"
      message="This product will be removed from the storefront while its inventory and order history remain available."
      confirm-label="Delete"
      busy-label="Deleting…"
      :busy="deleting"
      :close-on-confirm="false"
      danger
      @confirm="remove"
    />
  </div>
</template>
