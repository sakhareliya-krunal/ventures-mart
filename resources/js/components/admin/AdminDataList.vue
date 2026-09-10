<script setup>
import AdminAsyncState from './AdminAsyncState.vue';
defineProps({ rows: { type: Array, default: () => [] }, columns: { type: Array, default: () => [] }, rowKey: { type: String, default: 'id' }, loading: Boolean, refreshing: Boolean, error: String, searching: Boolean });
defineEmits(['retry', 'reset']);
</script>
<template>
  <div class="admin-data-list" :aria-busy="loading || refreshing">
    <div v-if="refreshing" class="admin-refresh-indicator" role="status">Updating results…</div>
    <AdminAsyncState
      :loading="loading"
      :error="error"
      :empty="!rows.length"
      :searching="searching"
      @retry="$emit('retry')"
      @reset="$emit('reset')"
    >
      <slot>
        <div v-if="columns.length" class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th v-for="column in columns" :key="column.key" scope="col">{{ column.label }}</th>
                <th v-if="$slots.actions" scope="col">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rows" :key="row[rowKey]">
                <td v-for="column in columns" :key="column.key" :data-label="column.label">
                  <slot :name="`cell-${column.key}`" :row="row" :value="row[column.key]">{{ row[column.key] ?? '—' }}</slot>
                </td>
                <td v-if="$slots.actions" data-label="Actions">
                  <div class="admin-actions"><slot name="actions" :row="row" /></div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </slot>
    </AdminAsyncState>
  </div>
</template>
