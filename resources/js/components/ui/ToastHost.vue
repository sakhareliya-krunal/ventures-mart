<script setup>
import { computed } from 'vue';
import { useUiStore } from '@/stores/ui';
import { formatCurrency } from '@/utils/format';

const ui = useUiStore();

const visible = computed(() => Boolean(ui.toast?.message || ui.toast?.title));
const typeClass = computed(() =>
  ui.toast?.type === 'error' ? 'app-toast--error' : 'app-toast--success',
);
const isOrderToast = computed(() => ui.toast?.variant === 'order');
const paymentMethodLabel = computed(() => {
  const method = ui.toast?.paymentMethod;
  if (method === 'cod') return 'Cash on Delivery';
  if (method === 'razorpay') return 'Pay online';
  return method || '';
});
const paymentStatusLabel = computed(() => {
  const status = ui.toast?.paymentStatus;
  if (!status) return '';
  return String(status).replaceAll('_', ' ').replace(/^\w/, (char) => char.toUpperCase());
});
const totalLabel = computed(() => {
  const total = ui.toast?.total;
  return total == null || total === '' ? '' : formatCurrency(total);
});
const orderIconStyle = {
  backgroundImage: "url('/favicon-48x48.png')",
};
</script>

<template>
  <Teleport to="body">
    <Transition name="app-toast">
      <div
        v-if="visible"
        class="app-toast"
        :class="[typeClass, { 'app-toast--order': isOrderToast }]"
        role="status"
        aria-live="polite"
      >
        <span
          v-if="isOrderToast"
          class="app-toast__icon"
          :style="orderIconStyle"
          aria-hidden="true"
        />
        <div class="app-toast__content">
          <template v-if="isOrderToast">
            <p class="app-toast__title">{{ ui.toast.title }}</p>
            <p v-if="ui.toast.message" class="app-toast__message">{{ ui.toast.message }}</p>
            <dl class="app-toast__order-details">
              <div v-if="ui.toast.orderNumber">
                <dt>Order</dt>
                <dd>{{ ui.toast.orderNumber }}</dd>
              </div>
              <div v-if="paymentMethodLabel">
                <dt>Payment</dt>
                <dd>
                  {{ paymentMethodLabel }}
                  <template v-if="paymentStatusLabel"> - {{ paymentStatusLabel }}</template>
                </dd>
              </div>
              <div v-if="totalLabel">
                <dt>Total</dt>
                <dd>{{ totalLabel }}</dd>
              </div>
            </dl>
            <a
              v-if="ui.toast.actionHref"
              class="app-toast__action"
              :href="ui.toast.actionHref"
              @click="ui.dismissToast()"
            >
              Track order
            </a>
          </template>
          <p v-else class="app-toast__message">{{ ui.toast.message }}</p>
        </div>
        <button
          type="button"
          class="app-toast__close"
          aria-label="Dismiss"
          @click="ui.dismissToast()"
        >
          &times;
        </button>
      </div>
    </Transition>
  </Teleport>
</template>
