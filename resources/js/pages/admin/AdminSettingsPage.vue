<script setup>
import { onMounted, reactive, ref } from 'vue';
import AdminSeoTab from '@/components/admin/AdminSeoTab.vue';
import AppButton from '@/components/ui/AppButton.vue';
import FormField from '@/components/ui/FormField.vue';
import api from '@/services/api';
import { blankSeoFields, buildSeoPayload, fillSeoFields, validateSeoFields } from '@/utils/adminSeo';
import { unwrapData } from '@/utils/format';

const passwordError = ref('');
const passwordSuccess = ref('');
const savingPassword = ref(false);
const seoError = ref('');
const seoSuccess = ref('');
const savingSeo = ref(false);
const redirectError = ref('');
const redirects = ref([]);

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

const homeSeoForm = reactive({
  seo: blankSeoFields(),
  faqs: [],
  seo_score: 0,
  suggested_links: [],
});

const redirectForm = reactive({
  old_path: '',
  target_path: '',
  status_code: 301,
  is_active: true,
});

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

async function loadSeo() {
  try {
    const [{ data: settingsData }, { data: homeData }, { data: redirectData }] = await Promise.all([
      api.get('/admin/seo/settings'),
      api.get('/admin/seo/pages/home'),
      api.get('/admin/seo/redirects'),
    ]);
    const settings = settingsData || {};
    Object.assign(seoSettings.site, settings.site || {});
    Object.assign(seoSettings.verification, settings.verification || {});
    Object.assign(seoSettings.analytics, settings.analytics || {});
    seoSettings.robots.enabled = settings.robots?.enabled !== false;
    seoSettings.robots.disallow_text = (settings.robots?.disallow || []).join('\n');
    seoSettings.sitemap.enabled = settings.sitemap?.enabled !== false;
    fillSeoFields(homeSeoForm, { seo: homeData });
    redirects.value = unwrapData(redirectData) || redirectData.data || [];
  } catch (err) {
    seoError.value =
      err.response?.data?.message ||
      'Unable to load SEO settings.';
  }
}

async function saveSeoSettings() {
  seoError.value = '';
  seoSuccess.value = '';
  const seoErrors = validateSeoFields(homeSeoForm);
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
    await api.patch('/admin/seo/pages/home', buildSeoPayload(homeSeoForm));
    seoSuccess.value = 'SEO settings updated.';
  } catch (err) {
    seoError.value =
      err.response?.data?.message ||
      Object.values(err.response?.data?.errors || {})[0]?.[0] ||
      'Unable to save SEO settings.';
  } finally {
    savingSeo.value = false;
  }
}

async function saveRedirect() {
  redirectError.value = '';
  try {
    await api.post('/admin/seo/redirects', { ...redirectForm });
    redirectForm.old_path = '';
    redirectForm.target_path = '';
    const { data } = await api.get('/admin/seo/redirects');
    redirects.value = unwrapData(data) || data.data || [];
  } catch (err) {
    redirectError.value =
      err.response?.data?.message ||
      Object.values(err.response?.data?.errors || {})[0]?.[0] ||
      'Unable to save redirect.';
  }
}

onMounted(loadSeo);
</script>

<template>
  <div class="admin-panel">
    <h2>SEO settings</h2>
    <p class="admin-muted">Manage site metadata, homepage SEO, analytics, robots, sitemap, and redirects.</p>
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
      <AdminSeoTab
        :form="homeSeoForm"
        fallback-title="Ventures Mart | Toys & lunch boxes"
        fallback-description="Shop toys, lunch boxes, and family essentials online at Ventures Mart."
        fallback-url="/"
      />
      <AppButton type="submit" :disabled="savingSeo">
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
      <AppButton type="submit">Add redirect</AppButton>
    </form>
    <div v-if="redirects.length" class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr><th>Old path</th><th>Target</th><th>Status</th><th>Hits</th></tr>
        </thead>
        <tbody>
          <tr v-for="redirect in redirects" :key="redirect.id">
            <td>{{ redirect.old_path }}</td>
            <td>{{ redirect.target_path }}</td>
            <td>{{ redirect.status_code }}</td>
            <td>{{ redirect.hit_count }}</td>
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
