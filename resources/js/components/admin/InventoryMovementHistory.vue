<script setup>
import { computed, onBeforeUnmount, watch } from 'vue';
import { X } from '@lucide/vue';
import AppButton from '@/components/ui/AppButton.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { useScrollLock } from '@/composables/useScrollLock';

const props = defineProps({
  open: { type: Boolean, default: false },
  product: { type: Object, default: null },
  movements: { type: Array, default: () => [] },
  meta: { type: Object, default: () => ({}) },
  loading: { type: Boolean, default: false },
  error: { type: String, default: '' },
});

const emit = defineEmits(['close', 'page']);
const currentPage = computed(() => Number(props.meta.current_page || props.meta.currentPage || 1));
const lastPage = computed(() => Number(props.meta.last_page || props.meta.lastPage || 1));

function number(value) {
  const parsed = Number(value || 0);
  return parsed > 0 ? `+${parsed}` : String(parsed);
}

function delta(movement, field) {
  return movement[`${field}_delta`] ?? movement.deltas?.[field] ?? 0;
}

function balance(movement, field) {
  return movement[`${field}_balance`] ?? movement.balances?.[field] ?? '—';
}

function date(value) {
  if (!value) return '—';
  const parsed = new Date(value);
  return Number.isNaN(parsed.getTime())
    ? value
    : new Intl.DateTimeFormat('en-IN', { dateStyle: 'medium', timeStyle: 'short' }).format(parsed);
}

function close() {
  emit('close');
}

function onKeydown(event) {
  if (event.key === 'Escape' && props.open) close();
}

useScrollLock('inventory-history', () => props.open);

watch(
  () => props.open,
  (open) => {
    if (open) window.addEventListener('keydown', onKeydown);
    else window.removeEventListener('keydown', onKeydown);
  },
);

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
  <div v-if="open" class="inventory-history" role="dialog" aria-modal="true" aria-label="Inventory movement history">
    <button class="inventory-history__backdrop" type="button" aria-label="Close history" @click="close" />
    <aside class="inventory-history__drawer">
      <header class="admin-modal__header">
        <div>
          <p class="admin-modal__eyebrow">Movement history</p>
          <h2>{{ product?.name || 'Inventory' }}</h2>
          <p class="admin-muted">{{ product?.sku || 'No SKU' }}</p>
        </div>
        <button class="admin-modal__close" type="button" aria-label="Close" @click="close">
          <X :size="19" />
        </button>
      </header>

      <div class="inventory-history__body">
        <LoadingSpinner v-if="loading" page label="Loading movements" />
        <p v-else-if="error" class="form-error" role="alert">{{ error }}</p>
        <ol v-else-if="movements.length" class="inventory-movements">
          <li v-for="movement in movements" :key="movement.id || movement.uuid" class="inventory-movement">
            <div class="inventory-movement__rail" aria-hidden="true"><span /></div>
            <div class="inventory-movement__content">
              <div class="inventory-movement__top">
                <span class="admin-badge admin-badge--info">
                  {{ String(movement.type || 'adjustment').replaceAll('_', ' ') }}
                </span>
                <time>{{ date(movement.occurred_at || movement.created_at) }}</time>
              </div>
              <p>{{ movement.reason || 'Inventory updated' }}</p>
              <div class="inventory-movement__deltas">
                <span :class="{ 'is-positive': Number(delta(movement, 'on_hand')) > 0, 'is-negative': Number(delta(movement, 'on_hand')) < 0 }">
                  On hand {{ number(delta(movement, 'on_hand')) }}
                </span>
                <span>Reserved {{ number(delta(movement, 'reserved')) }}</span>
                <span>Committed {{ number(delta(movement, 'committed')) }}</span>
              </div>
              <div class="inventory-movement__meta">
                <span v-if="movement.actor?.name || movement.actor_name">
                  By {{ movement.actor?.name || movement.actor_name }}
                </span>
                <span v-if="movement.order?.number || movement.order_number">
                  Order {{ movement.order?.number || movement.order_number }}
                </span>
                <span>Balance {{ balance(movement, 'on_hand') }}</span>
              </div>
            </div>
          </li>
        </ol>
        <p v-else class="admin-empty">No inventory movements found.</p>
      </div>

      <footer v-if="lastPage > 1" class="inventory-pagination inventory-history__pagination">
        <AppButton
          type="button"
          variant="secondary"
          size="sm"
          :disabled="loading || currentPage <= 1"
          @click="emit('page', currentPage - 1)"
        >
          Previous
        </AppButton>
        <span>Page {{ currentPage }} of {{ lastPage }}</span>
        <AppButton
          type="button"
          variant="secondary"
          size="sm"
          :disabled="loading || currentPage >= lastPage"
          @click="emit('page', currentPage + 1)"
        >
          Next
        </AppButton>
      </footer>
    </aside>
  </div>
</template>
