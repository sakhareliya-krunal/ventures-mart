<script setup>
import { onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AdminPostForm from '@/components/admin/AdminPostForm.vue';
import AppButton from '@/components/ui/AppButton.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import api from '@/services/api';
import { unwrapData } from '@/utils/format';
import {
  apiErrorMessage,
  blankPostForm,
  buildPostPayload,
  toDatetimeLocal,
  validatePostForm,
} from '@/utils/adminPostForm';
import { isNetworkOrTimeoutError } from '@/utils/apiError';

const route = useRoute();
const router = useRouter();
const loading = ref(true);
const saving = ref(false);
const error = ref('');
const loadError = ref('');
const fieldErrors = ref({});
const form = reactive(blankPostForm());

let networkRetryTimer = null;

function goBack() {
  router.push({ name: 'admin-posts' });
}

async function load() {
  loading.value = true;
  loadError.value = '';
  let holdLoader = false;
  try {
    const { data } = await api.get(`/admin/posts/${route.params.id}`);
    const full = unwrapData(data);
    Object.assign(form, {
      title: full.title || '',
      slug: full.slug || '',
      excerpt: full.excerpt || '',
      body: full.body || '',
      cover_image: full.cover_image || '',
      published_at: toDatetimeLocal(full.published_at),
    });
  } catch (err) {
    if (isNetworkOrTimeoutError(err)) {
      holdLoader = true;
      if (networkRetryTimer) clearTimeout(networkRetryTimer);
      networkRetryTimer = setTimeout(load, 1500);
      return;
    }
    loadError.value = apiErrorMessage(err, 'Unable to load post.');
  } finally {
    if (!holdLoader) loading.value = false;
  }
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

  try {
    await api.put(`/admin/posts/${route.params.id}`, buildPostPayload(form));
    await router.push({
      name: 'admin-posts',
      query: { notice: 'saved' },
    });
  } catch (err) {
    fieldErrors.value = err.response?.data?.errors || {};
    error.value = apiErrorMessage(err, 'Unable to save post.');
  } finally {
    saving.value = false;
  }
}

onMounted(load);

onBeforeUnmount(() => {
  if (networkRetryTimer) clearTimeout(networkRetryTimer);
});
</script>

<template>
  <div>
    <div class="admin-toolbar">
      <AppButton type="button" variant="ghost" @click="goBack">← Back to posts</AppButton>
    </div>

    <LoadingSpinner v-if="loading" page label="Loading post" />
    <div v-else-if="loadError" class="admin-panel">
      <p class="form-error">{{ loadError }}</p>
      <AppButton type="button" variant="ghost" @click="goBack">Back to posts</AppButton>
    </div>
    <template v-else>
      <div class="admin-panel">
        <h2>Edit post</h2>
        <p class="admin-muted">Update this blog post, then save to return to the list.</p>
      </div>
      <AdminPostForm
        :form="form"
        :field-errors="fieldErrors"
        :error="error"
        :saving="saving"
        submit-label="Save changes"
        @submit="save"
        @cancel="goBack"
      />
    </template>
  </div>
</template>
