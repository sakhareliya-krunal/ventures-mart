<script setup>
import { computed, reactive, watch } from 'vue';
import { X } from '@lucide/vue';
import AppButton from '@/components/ui/AppButton.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  item: { type: Object, default: null },
  busy: { type: Boolean, default: false },
  error: { type: String, default: '' },
});

const emit = defineEmits(['close', 'submit']);
const form = reactive({
  order_item_id: '',
  quantity: 1,
  disposition: 'restock',
  reason: '',
});

const maxReturnable = computed(() => {
  if (!props.item) return null;
  return Math.max(
    0,
    Number(props.item.shipped_quantity || 0) - Number(props.item.returned_quantity || 0),
  );
});
const valid = computed(
  () =>
    Number(form.order_item_id) > 0 &&
    Number.isInteger(Number(form.quantity)) &&
    Number(form.quantity) > 0 &&
    (maxReturnable.value === null || Number(form.quantity) <= maxReturnable.value) &&
    form.reason.trim().length >= 3,
);

watch(
  () => props.open,
  (open) => {
    if (!open) return;
    form.order_item_id = props.item?.id || '';
    form.quantity = 1;
    form.disposition = 'restock';
    form.reason = '';
  },
);

function close() {
  if (!props.busy) emit('close');
}

function submit() {
  if (!valid.value || props.busy) return;
  emit('submit', {
    order_item_id: Number(form.order_item_id),
    quantity: Number(form.quantity),
    disposition: form.disposition,
    reason: form.reason.trim(),
  });
}
</script>

<template>
  <div v-if="open" class="admin-modal inventory-return-dialog" role="dialog" aria-modal="true" aria-label="Process inventory return">
    <button class="admin-modal__backdrop" type="button" aria-label="Close return dialog" :disabled="busy" @click="close" />
    <div class="admin-modal__panel">
      <header class="admin-modal__header">
        <div>
          <p class="admin-modal__eyebrow">Inventory return</p>
          <h2>Process returned item</h2>
        </div>
        <button class="admin-modal__close" type="button" aria-label="Close" :disabled="busy" @click="close">
          <X :size="19" />
        </button>
      </header>

      <form class="admin-product-form inventory-return-form" @submit.prevent="submit">
        <div v-if="item" class="inventory-return-form__item">
          <strong>{{ item.name }}</strong>
          <span>{{ item.sku || 'No SKU' }}</span>
          <small>
            Shipped {{ item.shipped_quantity || 0 }} · already returned {{ item.returned_quantity || 0 }}
          </small>
        </div>

        <label class="admin-field">
          <span>Order item ID <em>*</em></span>
          <input v-model="form.order_item_id" type="number" min="1" step="1" :readonly="Boolean(item)" />
        </label>
        <label class="admin-field">
          <span>Quantity <em>*</em></span>
          <input
            v-model="form.quantity"
            type="number"
            min="1"
            :max="maxReturnable ?? undefined"
            step="1"
          />
          <small v-if="maxReturnable !== null" class="admin-muted">
            Up to {{ maxReturnable }} unit{{ maxReturnable === 1 ? '' : 's' }} can be returned.
          </small>
        </label>
        <label class="admin-field">
          <span>Disposition <em>*</em></span>
          <select v-model="form.disposition">
            <option value="restock">Restock for sale</option>
            <option value="damaged">Damaged / write off</option>
            <option value="inspection">Hold for inspection</option>
          </select>
        </label>
        <label class="admin-field">
          <span>Reason <em>*</em></span>
          <textarea v-model="form.reason" rows="3" maxlength="500" placeholder="Return condition and reason" />
        </label>
        <p v-if="error" class="form-error" role="alert">{{ error }}</p>

        <footer class="admin-modal__footer">
          <AppButton type="button" variant="ghost" :disabled="busy" @click="close">Cancel</AppButton>
          <AppButton type="submit" :disabled="busy || !valid">
            {{ busy ? 'Processing…' : 'Process return' }}
          </AppButton>
        </footer>
      </form>
    </div>
  </div>
</template>
