<script setup>
import AdminLoading from './AdminLoading.vue';
import AppButton from '@/components/ui/AppButton.vue';
defineProps({ loading: Boolean, error: String, empty: Boolean, searching: Boolean, label: { type: String, default: 'Loading' } });
defineEmits(['retry', 'reset']);
</script>
<template>
  <AdminLoading v-if="loading" :label="label" />
  <div v-else-if="error" class="admin-state" role="alert"><h3>Unable to load this section</h3><p>{{ error }}</p><AppButton variant="secondary" @click="$emit('retry')">Try again</AppButton></div>
  <div v-else-if="empty" class="admin-state"><h3>{{ searching ? 'No matching results' : 'Nothing here yet' }}</h3><p>{{ searching ? 'Try another search or clear the filters.' : 'Records will appear here when available.' }}</p><AppButton v-if="searching" variant="secondary" @click="$emit('reset')">Clear filters</AppButton></div>
  <slot v-else />
</template>
