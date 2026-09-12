<script setup>
import { ref } from 'vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { useAdminLoadingRegion } from '@/composables/useAdminLoadingRegion';

defineProps({ label: { type: String, default: 'Loading' }, page: Boolean });
const region = ref(null);
useAdminLoadingRegion(region);
</script>

<template>
    <div ref="region" class="admin-loading" aria-busy="true">
        <LoadingSpinner class="admin-loading__indicator" :label="label" />
    </div>
</template>
