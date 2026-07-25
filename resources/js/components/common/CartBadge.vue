<script setup>
defineProps({
  count: {
    type: Number,
    default: 0,
  },
});
</script>

<template>
  <span v-if="count">{{ count }}</span>
</template>
