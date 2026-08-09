<script setup>
import { computed } from 'vue';
import { Minus, Plus, Trash2 } from '@lucide/vue';
import { RouterLink } from 'vue-router';
import { formatCurrency } from '@/utils/format';
import { isOutOfStockProduct, maxCartQuantityFor } from '@/utils/cartStock';
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
const outOfStock = computed(() => isOutOfStockProduct(props.item));
const atMax = computed(() => props.item.quantity >= maxCartQuantityFor(props.item));

function decrease() {
  cart.bumpQuantity(props.item.product_id, -1);
}

function increase() {
  if (outOfStock.value) return;
  cart.bumpQuantity(props.item.product_id, 1);
}

function remove() {
  cart.removeItem(props.item.product_id);
}
</script>

<template>
  <div v-if="product" class="cart-line" :class="{ 'cart-line--oos': outOfStock }">
    <img :src="product.image" :alt="product.name" />
    <div class="cart-line__copy">
      <RouterLink :to="`/product/${product.slug}`">{{ product.name }}</RouterLink>
      <span>{{ formatCurrency(product.price) }}</span>
      <span v-if="outOfStock" class="cart-line__oos">Out of Stock</span>
    </div>
    <div
      class="cart-line__qty"
      :class="{ 'is-syncing': syncing, 'is-disabled': outOfStock }"
      role="group"
      :aria-label="`Quantity for ${product.name}`"
    >
      <button
        type="button"
        :aria-label="item.quantity <= 1 || outOfStock ? 'Remove item' : 'Decrease quantity'"
        @click="outOfStock ? remove() : decrease()"
      >
        <Trash2 v-if="item.quantity <= 1 || outOfStock" :size="16" />
        <Minus v-else :size="16" />
      </button>
      <span>{{ item.quantity }}</span>
      <button
        type="button"
        aria-label="Increase quantity"
        :disabled="atMax || outOfStock"
        @click="increase"
      >
        <Plus :size="16" />
      </button>
    </div>
    <strong>{{ formatCurrency(product.price * item.quantity) }}</strong>
    <button class="cart-line__remove" type="button" @click="remove">Remove</button>
  </div>
</template>
