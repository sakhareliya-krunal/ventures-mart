<script setup>
import { computed } from 'vue';
import { Minus, Plus, Trash2 } from '@lucide/vue';
import { RouterLink } from 'vue-router';
import { formatCurrency } from '@/utils/format';
import { useCartStore } from '@/stores/cart';

const props = defineProps({
  item: {
    type: Object,
    required: true,
  },
});

const cart = useCartStore();
const product = computed(() => props.item.product);
const syncing = computed(() => cart.isSyncing(props.item.product_id));
const atMax = computed(() => {
  const stock = Number(product.value?.stock);
  const stockCap = Number.isFinite(stock) && stock > 0 ? stock : 99;
  return props.item.quantity >= Math.min(99, stockCap);
});

function decrease() {
  cart.bumpQuantity(props.item.product_id, -1);
}

function increase() {
  cart.bumpQuantity(props.item.product_id, 1);
}

function remove() {
  cart.removeItem(props.item.product_id);
}
</script>

<template>
  <div v-if="product" class="cart-line">
    <img :src="product.image" :alt="product.name" />
    <div>
      <RouterLink :to="`/product/${product.slug}`">{{ product.name }}</RouterLink>
      <span>{{ formatCurrency(product.price) }}</span>
    </div>
    <div
      class="cart-line__qty"
      :class="{ 'is-syncing': syncing }"
      role="group"
      :aria-label="`Quantity for ${product.name}`"
    >
      <button
        type="button"
        :aria-label="item.quantity <= 1 ? 'Remove item' : 'Decrease quantity'"
        @click="decrease"
      >
        <Trash2 v-if="item.quantity <= 1" :size="16" />
        <Minus v-else :size="16" />
      </button>
      <span>{{ item.quantity }}</span>
      <button
        type="button"
        aria-label="Increase quantity"
        :disabled="atMax"
        @click="increase"
      >
        <Plus :size="16" />
      </button>
    </div>
    <strong>{{ formatCurrency(product.price * item.quantity) }}</strong>
    <button class="cart-line__remove" type="button" @click="remove">Remove</button>
  </div>
</template>
