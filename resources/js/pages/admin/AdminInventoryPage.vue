<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { AlertTriangle, Boxes, CheckSquare, Download, PackageCheck, PackageMinus, RefreshCw } from '@lucide/vue';
import { useRoute } from 'vue-router';
import AdminSearchField from '@/components/admin/AdminSearchField.vue';
import AdminPagination from '@/components/admin/AdminPagination.vue';
import InventoryAuditFlagsPanel from '@/components/admin/InventoryAuditFlagsPanel.vue';
import InventoryAdjustmentDialog from '@/components/admin/InventoryAdjustmentDialog.vue';
import InventoryMovementHistory from '@/components/admin/InventoryMovementHistory.vue';
import InventoryReturnsPanel from '@/components/admin/InventoryReturnsPanel.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppSelect from '@/components/ui/AppSelect.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import api from '@/services/api';
import { apiErrorMessage } from '@/utils/adminProductForm';

const route = useRoute();
const activeTab = ref(['stock', 'returns', 'audit'].includes(String(route.query.tab)) ? String(route.query.tab) : 'stock');
const loading = ref(true);
const refreshing = ref(false);
const exporting = ref(false);
const rows = ref([]);
const summary = ref({});
const meta = ref({});
const listError = ref('');
const successMessage = ref('');
const selectedIds = ref([]);
const adjustmentOpen = ref(false);
const adjusting = ref(false);
const adjustmentError = ref('');
const adjustmentProducts = ref([]);
const historyOpen = ref(false);
const historyProduct = ref(null);
const historyRows = ref([]);
const historyMeta = ref({});
const historyLoading = ref(false);
const historyError = ref('');
const filters = reactive({
  search: String(route.query.search || ''),
  status: String(route.query.status || ''),
  per_page: 10,
  page: 1,
});
const stockStatusOptions = [
  { value: '', label: 'All stock' },
  { value: 'in_stock', label: 'In stock' },
  { value: 'low_stock', label: 'Low stock' },
  { value: 'out_of_stock', label: 'Out of Stock' },
];

let searchTimer = null;
let successTimer = null;
let requestId = 0;

const currentPage = computed(() => Number(meta.value.current_page || meta.value.currentPage || filters.page || 1));
const lastPage = computed(() => Number(meta.value.last_page || meta.value.lastPage || 1));
const total = computed(() => Number(meta.value.total || rows.value.length));
const firstItem = computed(() => Number(meta.value.from || (total.value ? (currentPage.value - 1) * filters.per_page + 1 : 0)));
const lastItem = computed(() => Number(meta.value.to || Math.min(currentPage.value * filters.per_page, total.value)));
const allVisibleSelected = computed(
  () => rows.value.length > 0 && rows.value.every((row) => selectedIds.value.includes(row.id)),
);
const selectedProducts = computed(() => rows.value.filter((row) => selectedIds.value.includes(row.id)));

const kpis = computed(() => [
  {
    label: 'On hand',
    value: summaryValue('total_on_hand', 'on_hand'),
    detail: `${summaryValue('total_products', 'products', total.value)} products`,
    icon: Boxes,
  },
  {
    label: 'Available',
    value: summaryValue('total_available', 'available'),
    detail: 'Ready to sell',
    icon: PackageCheck,
  },
  {
    label: 'Reserved',
    value: summaryValue('total_reserved', 'reserved'),
    detail: 'Held for checkout',
    icon: CheckSquare,
  },
  {
    label: 'Committed',
    value: summaryValue('total_committed', 'committed'),
    detail: 'Allocated to orders',
    icon: PackageMinus,
  },
  {
    label: 'Low stock',
    value: summaryValue('low_stock_count', 'low_stock'),
    detail: `${summaryValue('out_of_stock_count', 'out_of_stock')} out of stock`,
    icon: AlertTriangle,
    tone: 'warn',
  },
]);

function summaryValue(...keys) {
  const fallback = typeof keys.at(-1) === 'number' ? keys.pop() : 0;
  for (const key of keys) {
    if (summary.value?.[key] !== undefined) return Number(summary.value[key]) || 0;
  }
  return fallback;
}

function normalizeRow(record) {
  const product = record.product || record;
  const balance = record.balance || record.inventory_balance || product.inventory_balance || {};
  const onHand = Number(record.on_hand ?? balance.on_hand ?? product.stock ?? 0);
  const reserved = Number(record.reserved ?? balance.reserved ?? 0);
  const committed = Number(record.committed ?? balance.committed ?? 0);
  const threshold = Number(record.low_stock_threshold ?? balance.low_stock_threshold ?? product.low_stock_threshold ?? 0);
  const reorder = Number(record.reorder_point ?? balance.reorder_point ?? product.reorder_point ?? threshold);
  const available = Number(record.available ?? balance.available ?? Math.max(0, onHand - reserved - committed));

  return {
    ...record,
    id: product.id ?? record.product_id,
    name: product.name || record.product_name || 'Unnamed product',
    sku: product.sku || record.sku || '',
    image: product.image || record.image || '',
    on_hand: onHand,
    reserved,
    committed,
    available,
    low_stock_threshold: threshold,
    reorder_point: reorder,
    status: record.status || (available <= 0 ? 'out_of_stock' : available <= threshold ? 'low_stock' : 'in_stock'),
  };
}

function normalizeCollection(payload) {
  const body = payload?.data ?? payload ?? {};
  const records = Array.isArray(body) ? body : body.data || body.items || body.inventory || [];
  const responseMeta = payload?.meta || body.meta || (body.current_page ? body : {});
  const responseSummary = payload?.summary || body.summary || {};
  return {
    rows: records.map(normalizeRow),
    meta: responseMeta,
    summary: responseSummary,
  };
}

function flash(message) {
  successMessage.value = message;
  if (successTimer) clearTimeout(successTimer);
  successTimer = setTimeout(() => (successMessage.value = ''), 4000);
}

function apiParams() {
  return {
    search: filters.search || undefined,
    status: filters.status || undefined,
    per_page: 10,
    page: filters.page,
  };
}

async function load({ silent = false } = {}) {
  const activeRequest = ++requestId;
  if (!silent) loading.value = true;
  else refreshing.value = true;
  listError.value = '';

  try {
    const { data } = await api.get('/admin/inventory', { params: apiParams(), skipErrorToast: true });
    if (activeRequest !== requestId) return;
    const normalized = normalizeCollection(data);
    rows.value = normalized.rows;
    meta.value = normalized.meta;
    if (Object.keys(normalized.summary).length) summary.value = normalized.summary;
    selectedIds.value = selectedIds.value.filter((id) => rows.value.some((row) => row.id === id));
  } catch (error) {
    if (activeRequest !== requestId) return;
    rows.value = [];
    listError.value = apiErrorMessage(error, 'Unable to load inventory.');
  } finally {
    if (activeRequest === requestId) {
      loading.value = false;
      refreshing.value = false;
    }
  }
}

async function loadSummary() {
  try {
    const { data } = await api.get('/admin/inventory/summary', { skipErrorToast: true });
    summary.value = data?.data || data?.summary || data || {};
  } catch {
    // The paginated endpoint may already include the summary.
  }
}

function changePage(page) {
  if (page < 1 || page > lastPage.value || page === currentPage.value) return;
  filters.page = page;
  load();
}

function toggleAll() {
  if (allVisibleSelected.value) {
    const visible = new Set(rows.value.map((row) => row.id));
    selectedIds.value = selectedIds.value.filter((id) => !visible.has(id));
  } else {
    selectedIds.value = [...new Set([...selectedIds.value, ...rows.value.map((row) => row.id)])];
  }
}

function toggleRow(id) {
  selectedIds.value = selectedIds.value.includes(id)
    ? selectedIds.value.filter((selected) => selected !== id)
    : [...selectedIds.value, id];
}

function openAdjustment(products) {
  adjustmentProducts.value = products;
  adjustmentError.value = '';
  adjustmentOpen.value = true;
}

async function submitAdjustment(values) {
  adjusting.value = true;
  adjustmentError.value = '';
  try {
    const key = () =>
      globalThis.crypto?.randomUUID?.() ||
      `inventory-${Date.now()}-${Math.random().toString(36).slice(2)}`;
    if (adjustmentProducts.value.length > 1) {
      const adjustments = adjustmentProducts.value.map((product) => ({
        product_id: product.id,
        ...values,
        expected_version: Number(product.version || 0),
        idempotency_key: key(),
      }));
      await api.post('/admin/inventory/bulk-adjustments', {
        product_ids: adjustmentProducts.value.map((product) => product.id),
        ...values,
        adjustments,
        idempotency_key: key(),
      });
    } else {
      const product = adjustmentProducts.value[0];
      await api.post(`/admin/inventory/${product.id}/adjustments`, {
        ...values,
        expected_version: Number(product.version || 0),
        idempotency_key: key(),
      });
    }
    const count = adjustmentProducts.value.length;
    adjustmentOpen.value = false;
    selectedIds.value = [];
    flash(count > 1 ? `${count} inventory records adjusted.` : 'Inventory adjusted.');
    await Promise.all([load({ silent: true }), loadSummary()]);
  } catch (error) {
    adjustmentError.value = apiErrorMessage(error, 'Unable to apply inventory adjustment.');
  } finally {
    adjusting.value = false;
  }
}

async function openHistory(product) {
  historyProduct.value = product;
  historyRows.value = [];
  historyMeta.value = {};
  historyError.value = '';
  historyOpen.value = true;
  await loadHistory(1);
}

async function loadHistory(page) {
  if (!historyProduct.value) return;
  historyLoading.value = true;
  historyError.value = '';
  try {
    const { data } = await api.get(`/admin/inventory/${historyProduct.value.id}/movements`, {
      params: { page, per_page: 20 },
      skipErrorToast: true,
    });
    const body = data?.data ?? data ?? {};
    historyRows.value = Array.isArray(body) ? body : body.data || body.items || body.movements || [];
    historyMeta.value = data?.meta || body.meta || (body.current_page ? body : {});
  } catch (error) {
    historyError.value = apiErrorMessage(error, 'Unable to load movement history.');
  } finally {
    historyLoading.value = false;
  }
}

async function exportCsv() {
  exporting.value = true;
  listError.value = '';
  try {
    const response = await api.get('/admin/inventory/export', {
      params: {
        search: filters.search || undefined,
        status: filters.status || undefined,
      },
      responseType: 'blob',
      skipErrorToast: true,
    });
    const blob = new Blob([response.data], { type: response.headers['content-type'] || 'text/csv' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `inventory-${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  } catch (error) {
    listError.value = apiErrorMessage(error, 'Unable to export inventory.');
  } finally {
    exporting.value = false;
  }
}

function stockBadge(row) {
  if (row.status === 'out_of_stock' || row.available <= 0) return ['Out of Stock', 'admin-badge--danger'];
  if (row.status === 'low_stock' || row.available <= row.low_stock_threshold) return ['Low stock', 'admin-badge--warn'];
  return ['In stock', 'admin-badge--ok'];
}

watch(
  () => filters.search,
  () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      filters.page = 1;
      load();
    }, 300);
  },
);

watch(
  () => filters.status,
  () => {
    filters.page = 1;
    load();
  },
);

onMounted(() => Promise.all([load(), loadSummary()]));
onBeforeUnmount(() => {
  if (searchTimer) clearTimeout(searchTimer);
  if (successTimer) clearTimeout(successTimer);
});
</script>

<template>
  <div class="inventory-page" :class="{ 'inventory-page--has-selection': selectedProducts.length }">
    <section class="inventory-heading">
      <div class="inventory-heading__copy">
        <h2>Inventory</h2>
        <p class="admin-muted">Monitor stock availability, allocations, and audit history.</p>
      </div>
      <div class="inventory-heading__actions">
        <AppButton type="button" variant="secondary" size="sm" :loading="refreshing" @click="load({ silent: true })">
          <RefreshCw :size="16" :class="{ 'inventory-spin': refreshing }" />
          Refresh
        </AppButton>
        <AppButton type="button" variant="secondary" size="sm" :loading="exporting" @click="exportCsv">
          <Download :size="16" />
          <span>Export CSV</span>
        </AppButton>
      </div>
    </section>

    <nav class="inventory-tabs" aria-label="Inventory sections">
      <button type="button" :aria-current="activeTab === 'stock' ? 'page' : undefined" :class="{ 'is-active': activeTab === 'stock' }" @click="activeTab = 'stock'">
        Stock
      </button>
      <button type="button" :aria-current="activeTab === 'returns' ? 'page' : undefined" :class="{ 'is-active': activeTab === 'returns' }" @click="activeTab = 'returns'">
        Returns
      </button>
      <button type="button" :aria-current="activeTab === 'audit' ? 'page' : undefined" :class="{ 'is-active': activeTab === 'audit' }" @click="activeTab = 'audit'">
        Audit flags
      </button>
    </nav>

    <section v-if="activeTab === 'stock'" class="inventory-kpis" aria-label="Inventory summary">
      <article v-for="kpi in kpis" :key="kpi.label" class="admin-kpi" :class="{ 'inventory-kpi--warn': kpi.tone === 'warn' }">
        <span class="admin-kpi__icon"><component :is="kpi.icon" :size="19" /></span>
        <span class="admin-kpi__body inventory-kpi__body">
          <span class="inventory-kpi__label">{{ kpi.label }}</span>
          <strong class="inventory-kpi__value">{{ kpi.value.toLocaleString('en-IN') }}</strong>
          <small class="inventory-kpi__detail">{{ kpi.detail }}</small>
        </span>
      </article>
    </section>

    <section v-if="activeTab === 'stock'" class="admin-panel inventory-stock-panel">
      <div class="admin-toolbar inventory-toolbar">
        <div class="admin-toolbar__filters inventory-toolbar__filters">
          <AdminSearchField
            v-model="filters.search"
            placeholder="Search name or SKU…"
            aria-label="Search inventory"
          />
          <AppSelect
            v-model="filters.status"
            :options="stockStatusOptions"
            placeholder="All stock"
            aria-label="Filter by stock status"
          />
        </div>
        <AppButton
          v-if="selectedProducts.length"
          type="button"
          size="sm"
          class="inventory-bulk-action inventory-bulk-action--desktop"
          @click="openAdjustment(selectedProducts)"
        >
          Adjust selected ({{ selectedProducts.length }})
        </AppButton>
      </div>

      <p v-if="successMessage" class="form-success" role="status">{{ successMessage }}</p>
      <p v-if="listError" class="form-error" role="alert">{{ listError }}</p>
      <LoadingSpinner v-if="loading" page label="Loading inventory" />

      <template v-else>
        <div class="admin-table-wrap inventory-table-wrap" tabindex="0" aria-label="Inventory records">
          <table class="admin-table inventory-table">
            <thead>
              <tr>
                <th class="inventory-table__check">
                  <input
                    type="checkbox"
                    :checked="allVisibleSelected"
                    aria-label="Select all visible products"
                    @change="toggleAll"
                  />
                </th>
                <th>Product</th>
                <th>On hand</th>
                <th>Reserved</th>
                <th>Committed</th>
                <th>Available</th>
                <th>Thresholds</th>
                <th>Status</th>
                <th />
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in rows"
                :key="row.id"
                class="inventory-table__row"
                :class="{ 'is-selected': selectedIds.includes(row.id) }"
              >
                <td class="inventory-table__check" data-label="Select">
                  <input
                    type="checkbox"
                    :checked="selectedIds.includes(row.id)"
                    :aria-label="`Select ${row.name}`"
                    @change="toggleRow(row.id)"
                  />
                </td>
                <td data-label="Product" class="inventory-table__product">
                  <div class="admin-product-cell inventory-product-cell">
                    <img v-if="row.image" class="admin-product-cell__thumb" :src="row.image" :alt="row.name" />
                    <div class="admin-product-cell__copy">
                      <strong>{{ row.name }}</strong>
                      <div class="admin-muted">{{ row.sku || 'No SKU' }}</div>
                    </div>
                  </div>
                </td>
                <td data-label="On hand" class="inventory-number inventory-table__metric inventory-table__metric--on-hand">{{ row.on_hand }}</td>
                <td data-label="Reserved" class="inventory-number inventory-table__metric inventory-table__metric--reserved">{{ row.reserved }}</td>
                <td data-label="Committed" class="inventory-number inventory-table__metric inventory-table__metric--committed">{{ row.committed }}</td>
                <td data-label="Available" class="inventory-number inventory-table__metric inventory-table__metric--available"><strong>{{ row.available }}</strong></td>
                <td data-label="Thresholds" class="inventory-table__thresholds">
                  <span class="inventory-thresholds">
                    Low {{ row.low_stock_threshold }}
                    <small>Reorder {{ row.reorder_point }}</small>
                  </span>
                </td>
                <td data-label="Status" class="inventory-table__status">
                  <span class="admin-badge" :class="stockBadge(row)[1]">{{ stockBadge(row)[0] }}</span>
                </td>
                <td data-label="Actions" class="inventory-table__actions">
                  <div class="admin-actions">
                    <AppButton type="button" variant="secondary" size="sm" @click="openHistory(row)">History</AppButton>
                    <AppButton type="button" size="sm" @click="openAdjustment([row])">Adjust</AppButton>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
          <p v-if="!rows.length" class="admin-empty">No inventory records match these filters.</p>
        </div>

        <AdminPagination
          v-if="total"
          :page="currentPage"
          :last-page="lastPage"
          :total="total"
          :from="firstItem"
          :to="lastItem"
          @page="changePage"
        />
      </template>
    </section>

    <aside
      v-if="activeTab === 'stock' && selectedProducts.length"
      class="inventory-mobile-bulk"
      aria-label="Selected inventory actions"
    >
      <span>
        <strong>{{ selectedProducts.length }}</strong>
        selected
      </span>
      <AppButton type="button" size="sm" @click="openAdjustment(selectedProducts)">
        Adjust stock
      </AppButton>
    </aside>

    <InventoryReturnsPanel v-if="activeTab === 'returns'" />
    <InventoryAuditFlagsPanel v-if="activeTab === 'audit'" />

    <InventoryAdjustmentDialog
      :open="adjustmentOpen"
      :products="adjustmentProducts"
      :busy="adjusting"
      :error="adjustmentError"
      @close="adjustmentOpen = false"
      @submit="submitAdjustment"
    />
    <InventoryMovementHistory
      :open="historyOpen"
      :product="historyProduct"
      :movements="historyRows"
      :meta="historyMeta"
      :loading="historyLoading"
      :error="historyError"
      @close="historyOpen = false"
      @page="loadHistory"
    />
  </div>
</template>
