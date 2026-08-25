<script setup>
import { computed, reactive, watch } from 'vue';
import { X } from '@lucide/vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppSelect from '@/components/ui/AppSelect.vue';
import { useScrollLock } from '@/composables/useScrollLock';

const props = defineProps({
  open: { type: Boolean, default: false },
  products: { type: Array, default: () => [] },
  busy: { type: Boolean, default: false },
  error: { type: String, default: '' },
});

const emit = defineEmits(['close', 'submit']);
const form = reactive({
  operation: 'receive',
  quantity: '',
  reason: '',
});

const operationOptions = [
  { value: 'receive', label: 'Receive stock' },
  { value: 'decrease', label: 'Remove stock' },
  { value: 'damage', label: 'Write off damaged stock' },
  { value: 'set_count', label: 'Set physical count' },
  { value: 'set_available', label: 'Set available quantity' },
  { value: 'mark_out_of_stock', label: 'Out of Stock' },
];

const isBulk = computed(() => props.products.length > 1);
const title = computed(() => (isBulk.value ? `Adjust ${props.products.length} products` : 'Adjust inventory'));
const isOutOfStock = computed(() => form.operation === 'mark_out_of_stock');
const quantity = computed(() => (isOutOfStock.value ? 0 : Number(form.quantity)));
const valid = computed(() => {
  if (String(form.reason || '').trim().length < 3) return false;
  if (isOutOfStock.value) return true;
  return (
    Number.isInteger(quantity.value) &&
    quantity.value >= 0 &&
    (['set_count', 'set_available'].includes(form.operation) || quantity.value > 0)
  );
});

useScrollLock('admin-modal-adjustment', () => props.open);

watch(
  () => props.open,
  (open) => {
    if (open) {
      form.operation = 'receive';
      form.quantity = '';
      form.reason = '';
    }
  },
);

function close() {
  if (!props.busy) emit('close');
}

function submit() {
  if (!valid.value || props.busy) return;
  emit('submit', {
    operation: isOutOfStock.value ? 'set_available' : form.operation,
    quantity: quantity.value,
    reason: form.reason.trim(),
  });
}
</script>

<template>
  <div v-if="open" class="admin-modal inventory-adjustment" role="dialog" aria-modal="true" :aria-label="title">
    <button class="admin-modal__backdrop" type="button" aria-label="Close dialog" :disabled="busy" @click="close" />
    <div class="admin-modal__panel">
      <header class="admin-modal__header">
        <div>
          <p class="admin-modal__eyebrow">{{ isBulk ? 'Bulk adjustment' : 'Stock adjustment' }}</p>
          <h2>{{ title }}</h2>
        </div>
        <button class="admin-modal__close" type="button" aria-label="Close" :disabled="busy" @click="close">
          <X :size="19" />
        </button>
      </header>

      <form class="admin-product-form" novalidate @submit.prevent="submit">
        <div class="inventory-adjustment__products">
          <div v-for="product in products" :key="product.id" class="inventory-adjustment__product">
            <span>
              <strong>{{ product.name }}</strong>
              <small>{{ product.sku || 'No SKU' }}</small>
            </span>
            <span class="admin-badge admin-badge--info">{{ product.on_hand ?? 0 }} on hand</span>
          </div>
        </div>

        <p class="admin-muted">The same operation is applied to every selected product.</p>

        <label class="admin-field">
          <span>Operation <em>*</em></span>
          <AppSelect
            v-model="form.operation"
            :options="operationOptions"
            placeholder="Select operation"
            aria-label="Stock adjustment operation"
          />
        </label>

        <label v-if="!isOutOfStock" class="admin-field">
          <span>Quantity <em>*</em></span>
          <input
            v-model="form.quantity"
            type="number"
            min="0"
            step="1"
            inputmode="numeric"
            placeholder="e.g. 12"
            autofocus
          />
          <small v-if="form.quantity !== '' && (!Number.isInteger(quantity) || quantity < 0)" class="admin-field__error">
            Enter a whole number of zero or more.
          </small>
        </label>
        <p v-else class="admin-muted inventory-adjustment__oos-note">
          Available quantity will be set to <strong>0</strong> (Out of Stock).
        </p>

        <label class="admin-field">
          <span>Reason <em>*</em></span>
          <textarea
            v-model="form.reason"
            rows="3"
            maxlength="500"
            placeholder="Why is this stock changing?"
          />
          <small class="admin-muted">This note is recorded in movement history.</small>
        </label>

        <p v-if="error" class="form-error" role="alert">{{ error }}</p>

        <footer class="admin-modal__footer">
          <AppButton type="button" variant="ghost" :disabled="busy" @click="close">Cancel</AppButton>
          <AppButton type="submit" :disabled="!valid" :loading="busy">
            {{ isOutOfStock ? 'Mark Out of Stock' : 'Apply adjustment' }}
          </AppButton>
        </footer>
      </form>
    </div>
  </div>
</template>
