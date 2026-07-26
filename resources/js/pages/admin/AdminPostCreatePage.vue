<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import AdminPostForm from '@/components/admin/AdminPostForm.vue';
import AppButton from '@/components/ui/AppButton.vue';
import api from '@/services/api';
import {
  apiErrorMessage,
  blankPostForm,
  buildPostPayload,
  validatePostForm,
} from '@/utils/adminPostForm';

const router = useRouter();
const saving = ref(false);
const error = ref('');
const fieldErrors = ref({});
const form = reactive(blankPostForm());

function goBack() {
  router.push({ name: 'admin-posts' });
}

async function save() {
  if (saving.value) return;

  const errors = validatePostForm(form);
  fieldErrors.value = errors;
  if (Object.keys(errors).length) {
    error.value = Object.values(errors)[0][0];
    return;
  }

  saving.value = true;
  error.value = '';
  fieldErrors.value = {};
  const payload = buildPostPayload(form);

  try {
    await api.post('/admin/posts', payload);
    await router.push({
      name: 'admin-posts',
      query: {
        notice: payload.published_at ? 'published' : 'draft',
      },
    });
  } catch (err) {
    fieldErrors.value = err.response?.data?.errors || {};
    error.value = apiErrorMessage(err, 'Unable to save post.');
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <div>
    <div class="admin-toolbar">
      <AppButton type="button" variant="ghost" @click="goBack">← Back to posts</AppButton>
    </div>
    <div class="admin-panel">
      <h2>New post</h2>
      <p class="admin-muted">Create a blog post. Set Published at to show it on the storefront.</p>
    </div>
    <AdminPostForm
      :form="form"
      :field-errors="fieldErrors"
      :error="error"
      :saving="saving"
      submit-label="Create post"
      @submit="save"
      @cancel="goBack"
    />
  </div>
</template>
