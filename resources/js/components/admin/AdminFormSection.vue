<script setup>
defineProps({ title: String, description: String });
</script>
<template><fieldset class="admin-form-section"><legend v-if="title">{{ title }}</legend><p v-if="description">{{ description }}</p><slot /></fieldset></template>
