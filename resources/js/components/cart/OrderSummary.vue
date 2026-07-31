<script setup>
import { computed } from 'vue';
import { formatCurrency } from '@/utils/format';

const props = defineProps({
  totals: {
    type: Object,
    required: true,
  },
});

const showIgst = computed(() => Number(props.totals.igst || 0) > 0);
const isEstimate = computed(() => props.totals.tax_type === 'estimate');
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
      <template v-if="showIgst">
        <div>
          <dt>IGST (5%)</dt>
          <dd>{{ formatCurrency(totals.igst) }}</dd>
        </div>
      </template>
      <template v-else>
        <div>
          <dt>CGST (2.5%)</dt>
          <dd>{{ formatCurrency(totals.cgst ?? (totals.tax || 0) / 2) }}</dd>
        </div>
        <div>
          <dt>SGST (2.5%)</dt>
          <dd>{{ formatCurrency(totals.sgst ?? (totals.tax || 0) / 2) }}</dd>
        </div>
      </template>
      <div class="summary-panel__total">
        <dt>Total</dt>
        <dd>{{ formatCurrency(totals.total) }}</dd>
      </div>
    </dl>
    <p v-if="isEstimate" class="summary-panel__gst-note">Estimated until shipping state is set</p>
  </aside>
</template>
