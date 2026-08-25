<script setup>
import { computed, onMounted, ref } from 'vue';
import { AlertTriangle, CheckCircle2 } from '@lucide/vue';
import AdminPagination from '@/components/admin/AdminPagination.vue';
import AppButton from '@/components/ui/AppButton.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import api from '@/services/api';
import { apiErrorMessage } from '@/utils/adminProductForm';

const rows = ref([]);
const meta = ref({});
const loading = ref(true);
const error = ref('');
const resolvingId = ref(null);
const includeResolved = ref(false);

const currentPage = computed(() => Number(meta.value.current_page || 1));
const lastPage = computed(() => Number(meta.value.last_page || 1));
const total = computed(() => Number(meta.value.total || rows.value.length));
const firstItem = computed(() => Number(meta.value.from || (total.value ? (currentPage.value - 1) * 10 + 1 : 0)));
const lastItem = computed(() => Number(meta.value.to || Math.min(currentPage.value * 10, total.value)));

function date(value) {
  if (!value) return '—';
  const parsed = new Date(value);
  return Number.isNaN(parsed.getTime()) ? value : parsed.toLocaleString('en-IN');
}

function contextEntries(context) {
  if (!context || typeof context !== 'object') return [];
  return Object.entries(context).slice(0, 4);
}

async function load(page = 1) {
  loading.value = true;
  error.value = '';
  try {
    const { data } = await api.get('/admin/inventory/audit-flags', {
      params: { page, per_page: 10, include_resolved: includeResolved.value ? 1 : 0 },
      skipErrorToast: true,
    });
    rows.value = data?.data || [];
    meta.value = data || {};
  } catch (err) {
    error.value = apiErrorMessage(err, 'Unable to load inventory audit flags.');
  } finally {
    loading.value = false;
  }
}

async function resolve(flag) {
  resolvingId.value = flag.id;
  error.value = '';
  try {
    await api.patch(`/admin/inventory/audit-flags/${flag.id}/resolve`);
    await load(currentPage.value);
  } catch (err) {
    error.value = apiErrorMessage(err, 'Unable to resolve audit flag.');
  } finally {
    resolvingId.value = null;
  }
}

function toggleResolved() {
  includeResolved.value = !includeResolved.value;
  load(1);
}

onMounted(() => load());
</script>

<template>
  <section class="admin-panel inventory-operations-panel">
    <div class="inventory-operations-toolbar">
      <div class="inventory-operations-toolbar__copy">
        <h2>Inventory audit flags</h2>
        <p class="admin-muted">Review inconsistencies that require a human decision.</p>
      </div>
      <label class="checkbox-row inventory-audit-toggle">
        <input type="checkbox" :checked="includeResolved" @change="toggleResolved" />
        <span>Include resolved</span>
      </label>
    </div>

    <p v-if="error" class="form-error" role="alert">{{ error }}</p>
    <LoadingSpinner v-if="loading" page label="Loading audit flags" />
    <template v-else>
      <div v-if="rows.length" class="inventory-audit-list">
        <article v-for="flag in rows" :key="flag.id" class="inventory-audit-card" :class="{ 'is-resolved': flag.resolved_at }">
          <span class="inventory-audit-card__icon" :class="{ 'is-resolved': flag.resolved_at }">
            <CheckCircle2 v-if="flag.resolved_at" :size="20" />
            <AlertTriangle v-else :size="20" />
          </span>
          <div class="inventory-audit-card__body">
            <div class="inventory-audit-card__heading">
              <div>
                <span class="admin-badge" :class="flag.resolved_at ? 'admin-badge--ok' : 'admin-badge--warn'">
                  {{ String(flag.code || 'audit').replaceAll('_', ' ') }}
                </span>
                <strong>{{ flag.product?.name || `Product #${flag.product_id || '—'}` }}</strong>
              </div>
              <time>{{ date(flag.created_at) }}</time>
            </div>
            <p>{{ flag.message }}</p>
            <div class="inventory-audit-card__meta">
              <span v-if="flag.product?.sku">SKU {{ flag.product.sku }}</span>
              <RouterLink v-if="flag.order_id" :to="`/admin/orders/${flag.order_id}`">
                Order {{ flag.order?.number || `#${flag.order_id}` }}
              </RouterLink>
              <span v-for="[key, value] in contextEntries(flag.context)" :key="key">
                {{ String(key).replaceAll('_', ' ') }}: {{ value }}
              </span>
              <span v-if="flag.resolved_at">Resolved {{ date(flag.resolved_at) }}</span>
            </div>
          </div>
          <AppButton
            v-if="!flag.resolved_at"
            type="button"
            variant="secondary"
            size="sm"
            :loading="resolvingId === flag.id"
            @click="resolve(flag)"
          >
            Mark resolved
          </AppButton>
        </article>
      </div>
      <p v-else class="admin-empty inventory-operations-empty">No {{ includeResolved ? '' : 'unresolved ' }}audit flags.</p>
      <AdminPagination
        v-if="total"
        :page="currentPage"
        :last-page="lastPage"
        :total="total"
        :from="firstItem"
        :to="lastItem"
        @page="load"
      />
    </template>
  </section>
</template>
