<script setup>
import { onMounted, reactive, ref } from 'vue';
import AdminSeoTab from '@/components/admin/AdminSeoTab.vue';
import AppButton from '@/components/ui/AppButton.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import api from '@/services/api';
import { blankSeoFields, buildSeoPayload, fillSeoFields, validateSeoFields } from '@/utils/adminSeo';
import { unwrapData } from '@/utils/format';

const loading = ref(true);
const saving = ref(false);
const error = ref('');
const categories = ref([]);
const editingId = ref(null);
const showForm = ref(false);
const confirmOpen = ref(false);
const pendingDeleteId = ref(null);
const deleting = ref(false);

const form = reactive({
  name: '',
  slug: '',
  description: '',
  image: '',
  featured: false,
  sort_order: 0,
  seo: blankSeoFields(),
  faqs: [],
  seo_score: 0,
  seo_checks: [],
  suggested_links: [],
});

function resetForm() {
  editingId.value = null;
  showForm.value = false;
  Object.assign(form, {
    name: '',
    slug: '',
    description: '',
    image: '',
    featured: false,
    sort_order: 0,
    seo: blankSeoFields(),
    faqs: [],
    seo_score: 0,
    seo_checks: [],
    suggested_links: [],
  });
}

function edit(category) {
  editingId.value = category.id;
  showForm.value = true;
  Object.assign(form, {
    name: category.name || '',
    slug: category.slug || '',
    description: category.description || '',
    image: category.image || '',
    featured: Boolean(category.featured),
    sort_order: category.sort_order || 0,
  });
  fillSeoFields(form, category);
}

async function load({ silent = false } = {}) {
  if (!silent) loading.value = true;
  try {
    const { data } = await api.get('/admin/categories');
    categories.value = unwrapData(data) || [];
  } finally {
    if (!silent) loading.value = false;
  }
}

async function save() {
  saving.value = true;
  error.value = '';
  try {
    const seoErrors = validateSeoFields(form);
    if (Object.keys(seoErrors).length) {
      error.value = Object.values(seoErrors)[0][0];
      saving.value = false;
      return;
    }
    const payload = { ...form, ...buildSeoPayload(form) };
    delete payload.seo_score;
    delete payload.seo_checks;
    delete payload.suggested_links;
    if (editingId.value) {
      await api.put(`/admin/categories/${editingId.value}`, payload);
    } else {
      await api.post('/admin/categories', payload);
    }
    resetForm();
    await load({ silent: true });
  } catch (err) {
    error.value =
      err.response?.data?.message ||
      Object.values(err.response?.data?.errors || {})[0]?.[0] ||
      'Unable to save category.';
  } finally {
    saving.value = false;
  }
}

async function requestRemove(id) {
  pendingDeleteId.value = id;
  confirmOpen.value = true;
}

async function remove() {
  if (!pendingDeleteId.value || deleting.value) return;
  deleting.value = true;
  try {
    await api.delete(`/admin/categories/${pendingDeleteId.value}`);
    pendingDeleteId.value = null;
    confirmOpen.value = false;
    await load({ silent: true });
  } finally {
    deleting.value = false;
  }
}

onMounted(load);
</script>

<template>
  <div>
    <div class="admin-panel">
      <div class="admin-toolbar">
        <h2>Categories</h2>
        <AppButton type="button" @click="showForm = true; editingId = null">Add category</AppButton>
      </div>
      <LoadingSpinner v-if="loading" page label="Loading categories" />
      <div v-else class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Slug</th>
              <th>Featured</th>
              <th>Sort</th>
              <th />
            </tr>
          </thead>
          <tbody>
            <tr v-for="category in categories" :key="category.id">
              <td data-label="Name">{{ category.name }}</td>
              <td data-label="Slug">{{ category.slug }}</td>
              <td data-label="Featured">{{ category.featured ? 'Yes' : 'No' }}</td>
              <td data-label="Sort">{{ category.sort_order }}</td>
              <td data-label="Actions">
                <div class="admin-actions">
                  <AppButton type="button" variant="secondary" size="sm" @click="edit(category)">
                    Edit
                  </AppButton>
                  <AppButton type="button" variant="danger" size="sm" @click="requestRemove(category.id)">
                    Delete
                  </AppButton>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="showForm" class="admin-panel">
      <h3>{{ editingId ? 'Edit category' : 'New category' }}</h3>
      <p v-if="error" class="form-error">{{ error }}</p>
      <form class="admin-form" @submit.prevent="save">
        <div class="admin-form__grid">
          <label>Name <input v-model="form.name" required /></label>
          <label>Slug <input v-model="form.slug" /></label>
          <label>Sort order <input v-model.number="form.sort_order" type="number" min="0" /></label>
          <label class="checkbox-row"><input v-model="form.featured" type="checkbox" /> Featured</label>
        </div>
        <label>Image URL <input v-model="form.image" /></label>
        <label>Description <textarea v-model="form.description" rows="3" /></label>
        <AdminSeoTab
          :form="form"
          bind-entity-slug
          :fallback-title="form.name ? `${form.name} Online | Ventures Mart` : 'Category Online | Ventures Mart'"
          :fallback-description="form.description"
          :fallback-url="form.slug ? `/category/${form.slug}` : '/category'"
        />
        <div class="admin-actions">
          <AppButton type="submit" :disabled="saving">{{ saving ? 'Saving…' : 'Save' }}</AppButton>
          <AppButton type="button" variant="ghost" @click="resetForm">Cancel</AppButton>
        </div>
      </form>
    </div>

    <ConfirmDialog
      v-model:open="confirmOpen"
      title="Delete category?"
      message="This category will be permanently removed."
      confirm-label="Delete"
      busy-label="Deleting…"
      :busy="deleting"
      :close-on-confirm="false"
      danger
      @confirm="remove"
    />
  </div>
</template>
