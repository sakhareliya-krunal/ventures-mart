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

async function decrease() {
  if (props.item.quantity <= 1) {
    await cart.removeItem(props.item.product_id);
    return;
  }

  await cart.updateQuantity(props.item.product_id, props.item.quantity - 1);
}

async function increase() {
  if (props.item.quantity >= 99) {
    return;
  }

  await cart.updateQuantity(props.item.product_id, props.item.quantity + 1);
}

async function remove() {
  await cart.removeItem(props.item.product_id);
}
</script>

<template>
  <div v-if="product" class="cart-line">
    <img :src="product.image" alt="" />
    <div>
      <RouterLink :to="`/product/${product.slug}`">{{ product.name }}</RouterLink>
      <span>{{ formatCurrency(product.price) }}</span>
    </div>
    <div class="cart-line__qty" role="group" :aria-label="`Quantity for ${product.name}`">
      <button
        type="button"
        :aria-label="item.quantity <= 1 ? 'Remove item' : 'Decrease quantity'"
        @click="decrease"
      >
        <Trash2 v-if="item.quantity <= 1" :size="14" />
        <Minus v-else :size="14" />
      </button>
      <span>{{ item.quantity }}</span>
      <button
        type="button"
        aria-label="Increase quantity"
        :disabled="item.quantity >= 99"
        @click="increase"
      >
        <Plus :size="14" />
      </button>
    </div>
    <strong>{{ formatCurrency(product.price * item.quantity) }}</strong>
    <button class="cart-line__remove" type="button" @click="remove">Remove</button>
  </div>
</template>
