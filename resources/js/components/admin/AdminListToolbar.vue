<script setup>
import AdminSearchField from './AdminSearchField.vue';
import AppButton from '@/components/ui/AppButton.vue';
defineProps({ modelValue: String, placeholder: String, label: String, total: Number, filtered: Boolean });
defineEmits(['update:modelValue', 'reset']);
</script>
<template>
  <div class="admin-list-toolbar">
    <AdminSearchField :model-value="modelValue" :placeholder="placeholder" :aria-label="label || 'Search records'" @update:model-value="$emit('update:modelValue', $event)" />
    <div class="admin-list-toolbar__filters"><slot /></div>
    <AppButton v-if="filtered" variant="ghost" size="sm" @click="$emit('reset')">Clear filters</AppButton>
    <span v-if="total != null" class="admin-result-count">{{ total.toLocaleString('en-IN') }} results</span>
  </div>
</template>
