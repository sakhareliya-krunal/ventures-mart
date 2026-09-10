<script setup>
defineProps({ label: String, tone: { type: String, default: 'neutral' } });
</script>
<template><span class="admin-status-badge" :data-tone="tone"><span aria-hidden="true" />{{ label }}<slot /></span></template>
