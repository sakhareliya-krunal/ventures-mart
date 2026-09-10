<script setup>
import AdminStatusBadge from '@/components/admin/AdminStatusBadge.vue';
import AppButton from '@/components/ui/AppButton.vue';
import { emailHref } from '@/utils/contactLinks';

defineProps({
  selected: { type: Object, required: true },
  updating: Boolean,
  statusTone: { type: Object, required: true },
  formatWhen: { type: Function, required: true },
  shortPath: { type: Function, required: true },
});

defineEmits(['setStatus', 'remove']);
</script>

<template>
  <div class="admin-errors-detail">
    <p>
      <AdminStatusBadge :label="selected.status" :tone="statusTone[selected.status] || 'neutral'" />
      <span class="admin-muted">{{ selected.uuid }}</span>
    </p>
    <p><strong>{{ selected.message }}</strong></p>
    <p class="admin-muted">
      {{ selected.category }} · seen {{ selected.occurrence_count || 1 }}× ·
      last {{ formatWhen(selected.last_seen_at) }}
    </p>
    <p v-if="selected.exception_class" class="admin-muted">{{ selected.exception_class }}</p>
    <p v-if="selected.file" class="admin-muted">
      {{ shortPath(selected.file) }}{{ selected.line ? `:${selected.line}` : '' }}
    </p>
    <p v-if="selected.url" class="admin-muted">{{ selected.method }} {{ selected.url }}</p>
    <p v-if="selected.user" class="admin-muted">
      User {{ selected.user.name }}
      <template v-if="selected.user.email">
        (<a :href="emailHref(selected.user.email)">{{ selected.user.email }}</a>)
      </template>
    </p>
    <p v-if="selected.ip || selected.user_agent" class="admin-muted">
      {{ selected.ip }}
      <template v-if="selected.user_agent"> · {{ selected.user_agent }}</template>
    </p>

    <div class="error-actions">
      <AppButton
        type="button"
        size="sm"
        :disabled="updating || selected.status === 'investigating'"
        @click="$emit('setStatus', 'investigating')"
      >
        Investigating
      </AppButton>
      <AppButton
        type="button"
        size="sm"
        :disabled="updating || selected.status === 'resolved'"
        @click="$emit('setStatus', 'resolved')"
      >
        Resolve
      </AppButton>
      <AppButton
        type="button"
        variant="ghost"
        size="sm"
        :disabled="updating || selected.status === 'ignored'"
        @click="$emit('setStatus', 'ignored')"
      >
        Ignore
      </AppButton>
      <AppButton
        type="button"
        variant="ghost"
        size="sm"
        :disabled="updating || selected.status === 'new'"
        @click="$emit('setStatus', 'new')"
      >
        Reopen
      </AppButton>
      <AppButton type="button" variant="danger" size="sm" @click="$emit('remove', selected.uuid)">
        Delete
      </AppButton>
    </div>

    <h4>Request</h4>
    <pre class="error-pre">{{ JSON.stringify(selected.request || {}, null, 2) }}</pre>

    <h4>Context</h4>
    <pre class="error-pre">{{ JSON.stringify(selected.context || {}, null, 2) }}</pre>

    <h4>Stack trace</h4>
    <pre class="error-pre">{{ selected.trace || 'No stack trace.' }}</pre>
  </div>
</template>

<style scoped>
.error-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin: 0.75rem 0 1rem;
}

.error-pre {
  margin: 0.5rem 0 1rem;
  padding: 0.75rem;
  max-height: 14rem;
  overflow: auto;
  font-size: 0.75rem;
  line-height: 1.4;
  white-space: pre-wrap;
  word-break: break-word;
  background: color-mix(in srgb, var(--admin-border, #d4d4d8) 35%, transparent);
  border-radius: 0.5rem;
}

.admin-errors-detail h4 {
  font-size: 0.85rem;
  margin: 0.75rem 0 0.35rem;
}
</style>
