<script setup>
defineProps({ modelValue: String, items: Array, label: { type: String, default: 'Sections' } });
defineEmits(['update:modelValue']);
</script>
<template><nav class="admin-tabs" :aria-label="label"><button v-for="item in items" :key="item.value" type="button" :aria-current="modelValue === item.value ? 'page' : undefined" :class="{ 'is-active': modelValue === item.value }" @click="$emit('update:modelValue', item.value)">{{ item.label }}</button></nav></template>
