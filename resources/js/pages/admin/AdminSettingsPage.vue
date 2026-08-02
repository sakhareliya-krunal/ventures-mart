<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import AdminSeoTab from '@/components/admin/AdminSeoTab.vue';
import AppButton from '@/components/ui/AppButton.vue';
import FormField from '@/components/ui/FormField.vue';
import api from '@/services/api';
import { blankSeoFields, buildSeoPayload, fillSeoFields, validateSeoFields } from '@/utils/adminSeo';
import { unwrapData } from '@/utils/format';

const STATIC_SEO_PAGES = [
  { key: 'home', label: 'Home', url: '/' },
  { key: 'shop', label: 'Shop', url: '/shop' },
  { key: 'about', label: 'About', url: '/about' },
  { key: 'contact', label: 'Contact', url: '/contact' },
  { key: 'blog', label: 'Blog', url: '/blog' },
  { key: 'shipping', label: 'Shipping', url: '/shipping' },
  { key: 'returns', label: 'Returns', url: '/returns' },
  { key: 'payments', label: 'Payments', url: '/payments' },
  { key: 'privacy-policy', label: 'Privacy policy', url: '/privacy-policy' },
  { key: 'terms', label: 'Terms', url: '/terms' },
  {
    key: 'shopping-confidence-shipping-replacement',
    label: 'Shopping confidence',
    url: '/shopping-confidence-shipping-replacement',
  },
];

const passwordError = ref('');
const passwordSuccess = ref('');
const savingPassword = ref(false);
const seoError = ref('');
const seoSuccess = ref('');
const savingSeo = ref(false);
const redirectError = ref('');
const redirects = ref([]);
const selectedPageKey = ref('home');
const loadingPageSeo = ref(false);
const editingRedirectId = ref(null);

const passwordForm = reactive({
  current_password: '',
  password: '',
  password_confirmation: '',
});

const seoSettings = reactive({
  site: {
    brand_name: 'Ventures Mart',
    tagline: '',
    default_locale: 'en-IN',
    default_robots: 'index,follow',
    default_og_image: '',
    logo: '',
    same_as: [],
  },
  verification: {
    google_site_verification: '',
  },
  analytics: {
    ga_measurement_id: '',
    gtm_container_id: '',
  },
  robots: {
    enabled: true,
    disallow_text: '/admin\n/checkout\n/cart\n/profile\n/orders',
  },
  sitemap: {
    enabled: true,
  },
});

const pageSeoForm = reactive({
  seo: blankSeoFields(),
  faqs: [],
  seo_score: 0,
  seo_checks: [],
  suggested_links: [],
});

const redirectForm = reactive({
  old_path: '',
  target_path: '',
  status_code: 301,
  is_active: true,
});

const selectedPage = computed(
  () => STATIC_SEO_PAGES.find((page) => page.key === selectedPageKey.value) || STATIC_SEO_PAGES[0],
);

async function savePassword() {
  passwordError.value = '';
  passwordSuccess.value = '';
  savingPassword.value = true;

  try {
    const { data } = await api.put('/profile/password', { ...passwordForm });
    passwordSuccess.value = data.message || 'Password updated.';
    passwordForm.current_password = '';
    passwordForm.password = '';
    passwordForm.password_confirmation = '';
  } catch (err) {
    passwordError.value =
      err.response?.data?.message ||
      Object.values(err.response?.data?.errors || {})[0]?.[0] ||
      'Unable to update password.';
  } finally {
    savingPassword.value = false;
  }
}

async function loadPageSeo(key = selectedPageKey.value) {
  loadingPageSeo.value = true;
  try {
    const { data } = await api.get(`/admin/seo/pages/${key}`);
    fillSeoFields(pageSeoForm, { seo: data });
  } catch (err) {
    seoError.value = err.response?.data?.message || 'Unable to load page SEO.';
  } finally {
    loadingPageSeo.value = false;
  }
}

async function loadRedirects() {
  const { data } = await api.get('/admin/seo/redirects');
  redirects.value = unwrapData(data) || data.data || [];
}

async function loadSeo() {
  try {
    const [{ data: settingsData }] = await Promise.all([
      api.get('/admin/seo/settings'),
      loadPageSeo('home'),
      loadRedirects(),
    ]);
    const settings = settingsData || {};
    Object.assign(seoSettings.site, settings.site || {});
    Object.assign(seoSettings.verification, settings.verification || {});
    Object.assign(seoSettings.analytics, settings.analytics || {});
    seoSettings.robots.enabled = settings.robots?.enabled !== false;
    seoSettings.robots.disallow_text = (settings.robots?.disallow || []).join('\n');
    seoSettings.sitemap.enabled = settings.sitemap?.enabled !== false;
  } catch (err) {
    seoError.value = err.response?.data?.message || 'Unable to load SEO settings.';
  }
}

async function saveSeoSettings() {
  seoError.value = '';
  seoSuccess.value = '';
  const seoErrors = validateSeoFields(pageSeoForm);
  if (Object.keys(seoErrors).length) {
    seoError.value = Object.values(seoErrors)[0][0];
    return;
  }
  savingSeo.value = true;
  try {
    await api.patch('/admin/seo/settings', {
      site: {
        ...seoSettings.site,
        same_as: Array.isArray(seoSettings.site.same_as) ? seoSettings.site.same_as : [],
      },
      verification: seoSettings.verification,
      analytics: seoSettings.analytics,
      robots: {
        enabled: seoSettings.robots.enabled,
        disallow: seoSettings.robots.disallow_text
          .split('\n')
          .map((item) => item.trim())
          .filter(Boolean),
      },
      sitemap: seoSettings.sitemap,
    });
    const { data } = await api.patch(
      `/admin/seo/pages/${selectedPageKey.value}`,
      buildSeoPayload(pageSeoForm),
    );
    fillSeoFields(pageSeoForm, { seo: data });
    seoSuccess.value = `SEO settings updated (${selectedPage.value.label}).`;
  } catch (err) {
    seoError.value =
      err.response?.data?.message ||
      Object.values(err.response?.data?.errors || {})[0]?.[0] ||
      'Unable to save SEO settings.';
  } finally {
    savingSeo.value = false;
  }
}

function resetRedirectForm() {
  editingRedirectId.value = null;
  redirectForm.old_path = '';
  redirectForm.target_path = '';
  redirectForm.status_code = 301;
  redirectForm.is_active = true;
}

function editRedirect(redirect) {
  editingRedirectId.value = redirect.id;
  redirectForm.old_path = redirect.old_path || '';
  redirectForm.target_path = redirect.target_path || '';
  redirectForm.status_code = redirect.status_code || 301;
  redirectForm.is_active = redirect.is_active !== false;
}

async function saveRedirect() {
  redirectError.value = '';
  try {
    if (editingRedirectId.value) {
      await api.patch(`/admin/seo/redirects/${editingRedirectId.value}`, { ...redirectForm });
    } else {
      await api.post('/admin/seo/redirects', { ...redirectForm });
    }
    resetRedirectForm();
    await loadRedirects();
  } catch (err) {
    redirectError.value =
      err.response?.data?.message ||
      Object.values(err.response?.data?.errors || {})[0]?.[0] ||
      'Unable to save redirect.';
  }
}

async function toggleRedirect(redirect) {
  redirectError.value = '';
  try {
    await api.patch(`/admin/seo/redirects/${redirect.id}`, {
      old_path: redirect.old_path,
      target_path: redirect.target_path,
      status_code: redirect.status_code,
      is_active: !redirect.is_active,
    });
    await loadRedirects();
  } catch (err) {
    redirectError.value = err.response?.data?.message || 'Unable to update redirect.';
  }
}

async function deleteRedirect(redirect) {
  if (!window.confirm(`Delete redirect ${redirect.old_path} → ${redirect.target_path}?`)) {
    return;
  }
  redirectError.value = '';
  try {
    await api.delete(`/admin/seo/redirects/${redirect.id}`);
    if (editingRedirectId.value === redirect.id) {
      resetRedirectForm();
    }
    await loadRedirects();
  } catch (err) {
    redirectError.value = err.response?.data?.message || 'Unable to delete redirect.';
  }
}

watch(selectedPageKey, (key) => {
  loadPageSeo(key);
});

onMounted(loadSeo);
</script>

<template>
  <div class="admin-panel">
    <h2>SEO settings</h2>
    <p class="admin-muted">Manage site metadata, page SEO, analytics, robots, sitemap, and redirects.</p>
    <p v-if="seoError" class="form-error">{{ seoError }}</p>
    <p v-if="seoSuccess" class="form-success">{{ seoSuccess }}</p>
    <form class="admin-form" @submit.prevent="saveSeoSettings">
      <div class="admin-form__grid">
        <label>Brand name <input v-model="seoSettings.site.brand_name" /></label>
        <label>Tagline <input v-model="seoSettings.site.tagline" /></label>
        <label>Default locale <input v-model="seoSettings.site.default_locale" /></label>
        <label>Default robots <input v-model="seoSettings.site.default_robots" /></label>
        <label>Default OG image <input v-model="seoSettings.site.default_og_image" /></label>
        <label>Logo URL <input v-model="seoSettings.site.logo" /></label>
        <label>Search Console verification <input v-model="seoSettings.verification.google_site_verification" /></label>
        <label>GA measurement ID <input v-model="seoSettings.analytics.ga_measurement_id" /></label>
        <label>GTM container ID <input v-model="seoSettings.analytics.gtm_container_id" /></label>
        <label class="checkbox-row"><input v-model="seoSettings.robots.enabled" type="checkbox" /> Enable robots.txt</label>
        <label class="checkbox-row"><input v-model="seoSettings.sitemap.enabled" type="checkbox" /> Enable sitemap.xml</label>
      </div>
      <label>Robots disallow paths <textarea v-model="seoSettings.robots.disallow_text" rows="5" /></label>

      <div class="admin-toolbar">
        <div>
          <h3>Page SEO</h3>
          <p class="admin-muted">{{ loadingPageSeo ? 'Loading page SEO…' : selectedPage.url }}</p>
        </div>
        <label>
          Page
          <select v-model="selectedPageKey">
            <option v-for="page in STATIC_SEO_PAGES" :key="page.key" :value="page.key">
              {{ page.label }}
            </option>
          </select>
        </label>
      </div>

      <AdminSeoTab
        :form="pageSeoForm"
        :fallback-title="`${seoSettings.site.brand_name} | ${selectedPage.label}`"
        fallback-description="Shop toys, lunch boxes, and family essentials online at Ventures Mart."
        :fallback-url="selectedPage.url"
      />
      <AppButton type="submit" :disabled="savingSeo || loadingPageSeo">
        {{ savingSeo ? 'Saving SEO...' : 'Save SEO settings' }}
      </AppButton>
    </form>
  </div>

  <div class="admin-panel">
    <h2>Redirects</h2>
    <p v-if="redirectError" class="form-error">{{ redirectError }}</p>
    <form class="admin-form" @submit.prevent="saveRedirect">
      <div class="admin-form__grid">
        <label>Old path <input v-model="redirectForm.old_path" placeholder="/old-product" required /></label>
        <label>Target path <input v-model="redirectForm.target_path" placeholder="/product/new-product" required /></label>
        <label>Status <input v-model.number="redirectForm.status_code" type="number" min="301" max="308" /></label>
        <label class="checkbox-row"><input v-model="redirectForm.is_active" type="checkbox" /> Active</label>
      </div>
      <div class="admin-toolbar">
        <AppButton type="submit">
          {{ editingRedirectId ? 'Update redirect' : 'Add redirect' }}
        </AppButton>
        <AppButton v-if="editingRedirectId" type="button" variant="secondary" @click="resetRedirectForm">
          Cancel edit
        </AppButton>
      </div>
    </form>
    <div v-if="redirects.length" class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Old path</th>
            <th>Target</th>
            <th>Status</th>
            <th>Active</th>
            <th>Hits</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="redirect in redirects" :key="redirect.id">
            <td>{{ redirect.old_path }}</td>
            <td>{{ redirect.target_path }}</td>
            <td>{{ redirect.status_code }}</td>
            <td>{{ redirect.is_active ? 'Yes' : 'No' }}</td>
            <td>{{ redirect.hit_count }}</td>
            <td>
              <div class="admin-toolbar">
                <AppButton type="button" size="sm" variant="secondary" @click="editRedirect(redirect)">
                  Edit
                </AppButton>
                <AppButton type="button" size="sm" variant="secondary" @click="toggleRedirect(redirect)">
                  {{ redirect.is_active ? 'Disable' : 'Enable' }}
                </AppButton>
                <AppButton type="button" size="sm" variant="danger" @click="deleteRedirect(redirect)">
                  Delete
                </AppButton>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <div class="admin-panel">
    <h2>Password</h2>
    <p class="admin-muted">Update your admin password.</p>
    <p v-if="passwordError" class="form-error">{{ passwordError }}</p>
    <p v-if="passwordSuccess" class="form-success">{{ passwordSuccess }}</p>
    <form class="admin-form" @submit.prevent="savePassword">
      <FormField
        v-model="passwordForm.current_password"
        label="Current password"
        type="password"
        required
        autocomplete="current-password"
      />
      <FormField
        v-model="passwordForm.password"
        label="New password"
        type="password"
        required
        autocomplete="new-password"
      />
      <FormField
        v-model="passwordForm.password_confirmation"
        label="Confirm password"
        type="password"
        required
        autocomplete="new-password"
      />
      <AppButton type="submit" :disabled="savingPassword">
        {{ savingPassword ? 'Updating…' : 'Update password' }}
      </AppButton>
    </form>
  </div>
</template>
