<script setup>
import { RouterLink } from 'vue-router';
defineProps({ title: String, description: String, breadcrumbs: { type: Array, default: () => [] } });
</script>
<template>
  <header class="admin-page-header">
    <div>
      <nav v-if="breadcrumbs.length" class="admin-breadcrumbs" aria-label="Breadcrumb">
        <template v-for="item in breadcrumbs" :key="item.label">
          <RouterLink v-if="item.to" :to="item.to">{{ item.label }}</RouterLink>
          <span v-else>{{ item.label }}</span><span aria-hidden="true">/</span>
        </template>
      </nav>
      <h1>{{ title }}</h1><p v-if="description">{{ description }}</p>
    </div>
    <div v-if="$slots.default" class="admin-page-header__actions"><slot /></div>
  </header>
</template>
