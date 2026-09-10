<script setup>
import AdminAsyncState from './AdminAsyncState.vue';
defineProps({ label: { type: String, default: 'Loading' }, page: Boolean });
</script>
<template><AdminAsyncState loading :label="label" /></template>
