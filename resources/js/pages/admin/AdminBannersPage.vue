<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { ChevronDown, ChevronUp, ImagePlus, Trash2 } from '@lucide/vue';
import AppButton from '@/components/ui/AppButton.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import api from '@/services/api';
import { unwrapData } from '@/utils/format';

const loading = ref(true);
const saving = ref(false);
const deleting = ref(false);
const uploadingField = ref('');
const error = ref('');
const successMessage = ref('');
const banners = ref([]);
const showForm = ref(false);
const editingId = ref(null);
const confirmOpen = ref(false);
const pendingDeleteId = ref(null);
const mobileInputRef = ref(null);
const webInputRef = ref(null);

const form = reactive({
  mobile_image: '',
  web_image: '',
  alt_text: 'Homepage banner',
  is_active: true,
});

const orderedBanners = computed(() =>
  [...banners.value].sort((a, b) => {
    const sort = Number(a.sort_order || 0) - Number(b.sort_order || 0);
    return sort || Number(a.id || 0) - Number(b.id || 0);
  }),
);

function resetForm() {
  editingId.value = null;
  showForm.value = false;
  error.value = '';
  Object.assign(form, {
    mobile_image: '',
    web_image: '',
    alt_text: 'Homepage banner',
    is_active: true,
  });
}

function flashSuccess(message) {
  successMessage.value = message;
  window.setTimeout(() => {
    if (successMessage.value === message) {
      successMessage.value = '';
    }
  }, 3500);
}

async function load({ silent = false } = {}) {
  if (!silent) loading.value = true;
  error.value = '';
  try {
    const { data } = await api.get('/admin/banners');
    banners.value = unwrapData(data) || [];
  } catch (err) {
    error.value =
      err.response?.data?.message ||
      Object.values(err.response?.data?.errors || {})[0]?.[0] ||
      'Unable to load banners.';
  } finally {
    if (!silent) loading.value = false;
  }
}

function addBanner() {
  resetForm();
  showForm.value = true;
}

function editBanner(banner) {
  editingId.value = banner.id;
  showForm.value = true;
  error.value = '';
  Object.assign(form, {
    mobile_image: banner.mobile_image || '',
    web_image: banner.web_image || '',
    alt_text: banner.alt_text || 'Homepage banner',
    is_active: banner.is_active !== false,
  });
}

function payload() {
  return {
    mobile_image: form.mobile_image,
    web_image: form.web_image,
    alt_text: form.alt_text || 'Homepage banner',
    is_active: Boolean(form.is_active),
    sort_order: editingId.value
      ? Number(orderedBanners.value.find((banner) => banner.id === editingId.value)?.sort_order || 0)
      : orderedBanners.value.length,
  };
}

async function save() {
  saving.value = true;
  error.value = '';
  try {
    if (editingId.value) {
      await api.put(`/admin/banners/${editingId.value}`, payload());
      flashSuccess('Banner saved.');
    } else {
      await api.post('/admin/banners', payload());
      flashSuccess('Banner created.');
    }
    resetForm();
    await load({ silent: true });
  } catch (err) {
    error.value =
      err.response?.data?.message ||
      Object.values(err.response?.data?.errors || {})[0]?.[0] ||
      'Unable to save banner.';
  } finally {
    saving.value = false;
  }
}

async function uploadImage(event, field) {
  const file = event.target.files?.[0];
  if (!file) return;

  uploadingField.value = field;
  error.value = '';

  const body = new FormData();
  body.append('purpose', 'banners');
  body.append('images[]', file);

  try {
    const { data } = await api.post('/admin/uploads/images', body);
    const url = data.urls?.[0];
    if (!url) throw new Error('Upload did not return an image URL.');
    form[field] = url;
  } catch (err) {
    error.value =
      err.response?.data?.message ||
      Object.values(err.response?.data?.errors || {})[0]?.[0] ||
      'Unable to upload image.';
  } finally {
    uploadingField.value = '';
    event.target.value = '';
  }
}

function removeImage(field) {
  form[field] = '';
}

function requestRemove(id) {
  pendingDeleteId.value = id;
  confirmOpen.value = true;
}

async function remove() {
  if (!pendingDeleteId.value || deleting.value) return;
  deleting.value = true;
  error.value = '';
  try {
    await api.delete(`/admin/banners/${pendingDeleteId.value}`);
    pendingDeleteId.value = null;
    confirmOpen.value = false;
    flashSuccess('Banner deleted.');
    await load({ silent: true });
  } catch (err) {
    error.value = err.response?.data?.message || 'Unable to delete banner.';
  } finally {
    deleting.value = false;
  }
}

async function moveBanner(index, delta) {
  const nextIndex = index + delta;
  const list = orderedBanners.value;
  if (nextIndex < 0 || nextIndex >= list.length) return;

  const current = list[index];
  const next = list[nextIndex];
  const currentSort = Number(current.sort_order || 0);
  const nextSort = Number(next.sort_order || 0);

  try {
    await Promise.all([
      api.put(`/admin/banners/${current.id}`, { sort_order: nextSort }),
      api.put(`/admin/banners/${next.id}`, { sort_order: currentSort }),
    ]);
    await load({ silent: true });
  } catch (err) {
    error.value = err.response?.data?.message || 'Unable to reorder banners.';
  }
}

function fieldLabel(field) {
  return field === 'mobile_image' ? 'Mobile Banner' : 'Web Banner';
}

onMounted(load);
</script>

<template>
  <div>
    <div class="admin-panel">
      <div class="admin-toolbar">
        <div>
          <h2>Banners</h2>
          <p class="admin-muted">Manage homepage carousel images.</p>
        </div>
        <AppButton type="button" @click="addBanner">Add banner</AppButton>
      </div>

      <p v-if="successMessage" class="form-success">{{ successMessage }}</p>
      <p v-if="error" class="form-error">{{ error }}</p>
      <LoadingSpinner v-if="loading" page label="Loading banners" />

      <div v-else class="admin-banner-index">
        <article v-for="(banner, index) in orderedBanners" :key="banner.id" class="admin-banner-card">
          <div class="admin-banner-card__media" aria-label="Banner previews">
            <figure class="admin-banner-card__preview admin-banner-card__preview--mobile">
              <img :src="banner.mobile_image" :alt="`${banner.alt_text} mobile`" />
              <figcaption>Mobile</figcaption>
            </figure>
            <figure class="admin-banner-card__preview admin-banner-card__preview--web">
              <img :src="banner.web_image" :alt="`${banner.alt_text} web`" />
              <figcaption>Web</figcaption>
            </figure>
          </div>

          <div class="admin-banner-card__body">
            <div class="admin-banner-card__heading">
              <div>
                <h3>{{ banner.alt_text }}</h3>
                <p>{{ banner.mobile_image }}</p>
              </div>
              <span :class="['admin-banner-status', banner.is_active ? 'is-active' : 'is-hidden']">
                {{ banner.is_active ? 'Active' : 'Hidden' }}
              </span>
            </div>

            <dl class="admin-banner-card__paths">
              <div>
                <dt>Mobile Banner</dt>
                <dd>{{ banner.mobile_image }}</dd>
              </div>
              <div>
                <dt>Web Banner</dt>
                <dd>{{ banner.web_image }}</dd>
              </div>
            </dl>
          </div>

          <div class="admin-banner-card__controls">
            <div class="admin-banner-sort" aria-label="Banner sort controls">
              <button type="button" class="icon-button" :disabled="index === 0" aria-label="Move up" @click="moveBanner(index, -1)">
                <ChevronUp :size="16" />
              </button>
              <button type="button" class="icon-button" :disabled="index === orderedBanners.length - 1" aria-label="Move down" @click="moveBanner(index, 1)">
                <ChevronDown :size="16" />
              </button>
            </div>
            <div class="admin-actions admin-banner-card__actions">
              <AppButton type="button" variant="secondary" size="sm" @click="editBanner(banner)">
                Edit
              </AppButton>
              <AppButton type="button" variant="danger" size="sm" @click="requestRemove(banner.id)">
                Delete
              </AppButton>
            </div>
          </div>
        </article>
        <p v-if="!orderedBanners.length" class="admin-empty">No banners found.</p>
      </div>
    </div>

    <div v-if="showForm" class="admin-panel">
      <h3>{{ editingId ? 'Edit banner' : 'New banner' }}</h3>
      <form novalidate class="admin-form" @submit.prevent="save">
        <div class="admin-form__grid">
          <label class="admin-field">
            <span>Alt text</span>
            <input v-model="form.alt_text" autocomplete="off" />
          </label>
          <label class="checkbox-row">
            <input v-model="form.is_active" type="checkbox" />
            Active
          </label>
        </div>

        <div class="admin-banner-fields">
          <section
            v-for="field in ['mobile_image', 'web_image']"
            :key="field"
            class="admin-banner-field"
          >
            <div class="admin-toolbar">
              <h4>{{ fieldLabel(field) }} <em>*</em></h4>
              <div class="admin-actions">
                <AppButton
                  type="button"
                  variant="secondary"
                  size="sm"
                  :loading="uploadingField === field"
                  :disabled="saving || !!uploadingField"
                  @click="field === 'mobile_image' ? mobileInputRef?.click() : webInputRef?.click()"
                >
                  <ImagePlus :size="16" />
                  Upload
                </AppButton>
                <AppButton
                  v-if="form[field]"
                  type="button"
                  variant="ghost"
                  size="sm"
                  :disabled="saving || !!uploadingField"
                  @click="removeImage(field)"
                >
                  <Trash2 :size="16" />
                  Remove
                </AppButton>
              </div>
            </div>

            <img v-if="form[field]" class="admin-banner-field__preview" :src="form[field]" :alt="`${fieldLabel(field)} preview`" />
            <div v-else class="admin-image-dropzone admin-banner-field__empty">
              <ImagePlus :size="22" aria-hidden="true" />
              <div>
                <strong>Add image</strong>
                <p>Upload JPEG, PNG, or WebP up to 4MB.</p>
              </div>
            </div>

            <label class="admin-field">
              <span>{{ fieldLabel(field) }} path / URL</span>
              <input v-model="form[field]" autocomplete="off" placeholder="/storage/banners/..." />
            </label>
          </section>
        </div>

        <input
          ref="mobileInputRef"
          class="admin-image-dropzone__input"
          type="file"
          accept="image/jpeg,image/png,image/webp"
          @change="uploadImage($event, 'mobile_image')"
        />
        <input
          ref="webInputRef"
          class="admin-image-dropzone__input"
          type="file"
          accept="image/jpeg,image/png,image/webp"
          @change="uploadImage($event, 'web_image')"
        />

        <div class="admin-actions">
          <AppButton type="submit" :loading="saving" :disabled="!!uploadingField">
            Save
          </AppButton>
          <AppButton type="button" variant="ghost" :disabled="saving" @click="resetForm">
            Cancel
          </AppButton>
        </div>
      </form>
    </div>

    <ConfirmDialog
      v-model:open="confirmOpen"
      title="Delete banner?"
      message="This banner will be removed from the homepage carousel."
      confirm-label="Delete"
      busy-label="Deleting..."
      :busy="deleting"
      :close-on-confirm="false"
      danger
      @confirm="remove"
    />
  </div>
</template>
