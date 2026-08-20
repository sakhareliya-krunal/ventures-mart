<script setup>
import { Heart } from '@lucide/vue';

defineProps({
  badge: {
    type: String,
    default: '',
  },
  wished: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['toggle-wish']);

function onToggleWish() {
  emit('toggle-wish');
}
</script>

<template>
  <div class="product-detail__gallery-chrome">
    <span v-if="badge" class="product-detail__badge-overlay">
      {{ badge }}
    </span>
    <button
      type="button"
      class="product-detail__wish-icon"
      :class="{ 'is-wished': wished }"
      :aria-pressed="wished"
      :aria-label="wished ? 'Remove from wishlist' : 'Add to wishlist'"
      @click="onToggleWish"
    >
      <Heart :size="20" :fill="wished ? 'currentColor' : 'none'" aria-hidden="true" />
    </button>
  </div>
</template>
