import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '@/services/api';

export const useAdminNavigationCountsStore = defineStore('adminNavigationCounts', () => {
  const inventoryUnread = ref(0);
  const contactUnread = ref(0);
  let refreshPromise = null;

  function apply(data = {}) {
    inventoryUnread.value = Math.max(0, Number(data.inventory_unread_count || 0));
    contactUnread.value = Math.max(0, Number(data.contact_unread_count || 0));
  }

  async function refresh() {
    if (refreshPromise) return refreshPromise;

    refreshPromise = api
      .get('/admin/navigation-counts', { skipErrorToast: true })
      .then(({ data }) => apply(data))
      .catch(() => {
        // Preserve the last confirmed counts during transient failures.
      })
      .finally(() => {
        refreshPromise = null;
      });

    return refreshPromise;
  }

  async function markInventoryRead() {
    if (!inventoryUnread.value) return;

    try {
      await api.patch('/admin/notifications/inventory/read-all', {}, { skipErrorToast: true });
      inventoryUnread.value = 0;
    } catch {
      // Keep the badge until the server confirms the update.
    }
  }

  function setContactUnread(count) {
    contactUnread.value = Math.max(0, Number(count || 0));
  }

  return {
    inventoryUnread,
    contactUnread,
    refresh,
    markInventoryRead,
    setContactUnread,
  };
});
