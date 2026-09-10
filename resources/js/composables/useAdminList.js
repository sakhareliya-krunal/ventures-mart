import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '@/services/api';

export function useAdminList(endpoint, {
  fixedParams = {},
  filterDefaults = {},
  perPage = 20,
  searchParam = 'search',
  searchQueryKey = 'search',
  skipErrorToast = false,
} = {}) {
  const route = useRoute();
  const router = useRouter();
  const rows = ref([]);
  const loading = ref(true);
  const refreshing = ref(false);
  const error = ref('');
  const search = ref(String(route.query[searchQueryKey] || route.query.search || ''));
  const filters = ref(Object.fromEntries(Object.entries(filterDefaults).map(([key, value]) => [key, String(route.query[key] ?? value)])));
  const page = ref(Math.max(1, parseInt(route.query.page, 10) || 1));
  const meta = ref({ current_page: page.value, last_page: 1, total: 0, from: 0, to: 0 });
  const filtered = computed(() => Boolean(search.value || Object.entries(filters.value).some(([key, value]) => value !== filterDefaults[key])));
  let timer;
  let controller;
  let generation = 0;
  let syncing = false;
  let disposed = false;

  async function load() {
    const request = ++generation;
    controller?.abort();
    controller = new AbortController();
    error.value = '';
    refreshing.value = !loading.value;
    try {
      const filterParams = Object.fromEntries(
        Object.entries(filters.value).filter(([, value]) => value !== '' && value != null),
      );
      const { data } = await api.get(endpoint, {
        params: {
          ...fixedParams,
          ...filterParams,
          ...(search.value ? { [searchParam]: search.value } : {}),
          page: page.value,
          per_page: perPage,
        },
        signal: controller.signal,
        skipErrorToast,
      });
      if (disposed || request !== generation) return;
      const payload = data?.data ?? data;
      rows.value = Array.isArray(payload) ? payload : [];
      meta.value = data?.meta || { current_page: data?.current_page || 1, last_page: data?.last_page || 1, total: data?.total ?? rows.value.length, from: rows.value.length ? 1 : 0, to: rows.value.length };
      if (page.value > meta.value.last_page && meta.value.last_page > 0) go(meta.value.last_page);
    } catch (err) {
      if (disposed || request !== generation || err.code === 'ERR_CANCELED') return;
      error.value = err.response?.data?.message || 'Please check your connection and try again.';
    } finally {
      if (request === generation && !disposed) { loading.value = false; refreshing.value = false; }
    }
  }
  async function updateQuery() {
    if (disposed) return;
    const query = {
      ...route.query,
      ...filters.value,
      [searchQueryKey]: search.value || undefined,
      page: page.value > 1 ? page.value : undefined,
    };
    if (searchQueryKey !== 'search') delete query.search;
    for (const [key, value] of Object.entries(filterDefaults)) if (query[key] === value) delete query[key];
    await router.replace({ query });
    if (!disposed) load();
  }
  function go(value) { page.value = Math.max(1, Number(value) || 1); clearTimeout(timer); updateQuery(); }
  function reset() { search.value = ''; filters.value = { ...filterDefaults }; }
  watch(search, () => { if (syncing) return; controller?.abort(); generation++; clearTimeout(timer); page.value = 1; timer = setTimeout(updateQuery, 300); });
  watch(filters, () => { if (syncing) return; clearTimeout(timer); page.value = 1; updateQuery(); }, { deep: true });
  watch(() => route.query, (query) => {
    const nextSearch = String(query[searchQueryKey] || query.search || '');
    const nextPage = Math.max(1, parseInt(query.page, 10) || 1);
    const nextFilters = Object.fromEntries(Object.entries(filterDefaults).map(([key, value]) => [key, String(query[key] ?? value)]));
    if (nextSearch === search.value && nextPage === page.value && JSON.stringify(nextFilters) === JSON.stringify(filters.value)) return;
    syncing = true;
    search.value = nextSearch; page.value = nextPage; filters.value = nextFilters;
    clearTimeout(timer);
    queueMicrotask(() => { syncing = false; if (!disposed) load(); });
  });
  onMounted(load);
  onBeforeUnmount(() => { disposed = true; generation++; clearTimeout(timer); controller?.abort(); });
  return { rows, loading, refreshing, error, search, filters, page, meta, filtered, load, go, reset };
}
