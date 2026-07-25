<script setup>
import { formatCurrency } from '@/utils/format';

defineProps({
  totals: {
    type: Object,
    required: true,
  },
});
</script>

<template>
  <aside class="summary-panel">
    <h2>Order summary</h2>
    <dl>
      <div>
        <dt>Subtotal</dt>
        <dd>{{ formatCurrency(totals.subtotal) }}</dd>
      </div>
      <div>
        <dt>Shipping</dt>
        <dd>{{ totals.shipping ? formatCurrency(totals.shipping) : 'Free' }}</dd>
      </div>
      <div>
        <dt>Tax</dt>
        <dd>{{ formatCurrency(totals.tax) }}</dd>
      </div>
      <div class="summary-panel__total">
        <dt>Total</dt>
        <dd>{{ formatCurrency(totals.total) }}</dd>
      </div>
    </dl>
  </aside>
</template>
