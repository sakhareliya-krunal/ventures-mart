<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import {
  FileText,
  Mail,
  Package,
  ShoppingBag,
  TrendingUp,
  Users,
} from '@lucide/vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import api from '@/services/api';
import { orderStatusBadgeClass, orderStatusLabel } from '@/utils/adminBadges';
import { formatCurrency, unwrapData } from '@/utils/format';

const revenueRanges = [
  { key: 'day', label: 'Day' },
  { key: 'week', label: 'Week' },
  { key: 'month', label: 'Month' },
  { key: 'year', label: 'Year' },
];

const loading = ref(true);
const chartLoading = ref(false);
const revenueRange = ref('week');
const stats = ref({
  orders_count: 0,
  products_count: 0,
  users_count: 0,
  customers_count: 0,
  contact_messages_count: 0,
  posts_count: 0,
  published_posts_count: 0,
  categories_count: 0,
  orders_today: 0,
  revenue_today: 0,
  orders_this_week: 0,
  revenue_this_week: 0,
  revenue_total: 0,
  orders_by_status: {
    Processing: 0,
    Packed: 0,
    Shipped: 0,
    Delivered: 0,
    Cancelled: 0,
  },
  revenue_range: 'week',
  revenue_period_label: 'Last 7 days',
  revenue_period_total: 0,
  revenue_period_orders: 0,
  revenue_series: [],
  revenue_last_7_days: [],
  low_stock_products: [],
  recent_messages: [],
  recent_posts: [],
  recent_orders: [],
});

const statusOrder = ['Processing', 'Packed', 'Shipped', 'Delivered', 'Cancelled'];

const revenueSeries = computed(() =>
  Array.isArray(stats.value.revenue_series) && stats.value.revenue_series.length
    ? stats.value.revenue_series
    : stats.value.revenue_last_7_days || [],
);

const maxRevenuePoint = computed(() =>
  Math.max(1, ...revenueSeries.value.map((point) => Number(point.total) || 0)),
);

const statusTotal = computed(() =>
  statusOrder.reduce((sum, key) => sum + (Number(stats.value.orders_by_status?.[key]) || 0), 0),
);

const kpis = computed(() => [
  {
    key: 'revenue',
    label: 'Revenue',
    value: formatCurrency(stats.value.revenue_total),
    hint: `${formatCurrency(stats.value.revenue_this_week)} this week`,
    icon: TrendingUp,
  },
  {
    key: 'orders',
    label: 'Orders',
    value: String(stats.value.orders_count),
    hint: `${stats.value.orders_this_week} this week · ${stats.value.orders_today} today`,
    icon: ShoppingBag,
  },
  {
    key: 'products',
    label: 'Products',
    value: String(stats.value.products_count),
    hint: `${stats.value.categories_count} categories`,
    icon: Package,
  },
  {
    key: 'customers',
    label: 'Customers',
    value: String(stats.value.customers_count),
    hint: `${stats.value.users_count} total users`,
    icon: Users,
  },
  {
    key: 'messages',
    label: 'Messages',
    value: String(stats.value.contact_messages_count),
    hint: 'Contact inbox',
    icon: Mail,
  },
  {
    key: 'posts',
    label: 'Posts',
    value: String(stats.value.posts_count),
    hint: `${stats.value.published_posts_count} published`,
    icon: FileText,
  },
]);

function formatDate(value) {
  if (!value) return '—';
  return new Intl.DateTimeFormat('en-IN', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  }).format(new Date(value));
}

function formatTime(value) {
  if (!value) return '—';
  return new Intl.DateTimeFormat('en-IN', {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value));
}

function snippet(text, max = 72) {
  const clean = String(text || '').replace(/\s+/g, ' ').trim();
  if (clean.length <= max) return clean;
  return `${clean.slice(0, max).trimEnd()}…`;
}

function statusShare(status) {
  const count = Number(stats.value.orders_by_status?.[status]) || 0;
  if (!statusTotal.value) return 0;
  return Math.round((count / statusTotal.value) * 100);
}

function barHeight(total) {
  return `${Math.max(6, Math.round((Number(total) / maxRevenuePoint.value) * 100))}%`;
}

function isPublished(post) {
  if (!post?.published_at) return false;
  return new Date(post.published_at).getTime() <= Date.now();
}

function pointKey(point, index) {
  return point.key || point.date || `point-${index}`;
}

function applyStats(data) {
  stats.value = {
    ...stats.value,
    ...data,
    orders_by_status: {
      ...stats.value.orders_by_status,
      ...(data.orders_by_status || {}),
    },
    revenue_series: data.revenue_series || [],
    revenue_last_7_days: data.revenue_last_7_days || [],
    low_stock_products: data.low_stock_products || [],
    recent_messages: data.recent_messages || [],
    recent_posts: data.recent_posts || [],
    recent_orders: unwrapData(data.recent_orders) || data.recent_orders || [],
  };
  if (data.revenue_range) {
    revenueRange.value = data.revenue_range;
  }
}

async function loadStats({ silent = false } = {}) {
  if (silent) chartLoading.value = true;
  else loading.value = true;

  try {
    const { data } = await api.get('/admin/stats', {
      params: { range: revenueRange.value },
    });
    applyStats(data);
  } finally {
    loading.value = false;
    chartLoading.value = false;
  }
}

function setRevenueRange(range) {
  if (range === revenueRange.value || chartLoading.value) return;
  revenueRange.value = range;
}

watch(revenueRange, async (next, prev) => {
  if (!prev || next === prev) return;
  await loadStats({ silent: true });
});

onMounted(() => loadStats());
</script>

<template>
  <div class="admin-dash">
    <LoadingSpinner v-if="loading" page label="Loading dashboard" />
    <template v-else>
      <header class="admin-dash-header">
        <div>
          <h1>Dashboard</h1>
          <p class="admin-muted">Store overview — orders, catalog, blog, and inbox at a glance.</p>
        </div>
        <div class="admin-dash-header__actions">
          <RouterLink class="button button--secondary button--sm" to="/admin/products/create">
            Add product
          </RouterLink>
          <RouterLink class="button button--secondary button--sm" to="/admin/posts/create">
            New post
          </RouterLink>
          <RouterLink class="button button--sm" to="/admin/orders">View orders</RouterLink>
        </div>
      </header>

      <section class="admin-dash-kpis" aria-label="Key metrics">
        <component
          :is="kpi.to ? RouterLink : 'article'"
          v-for="kpi in kpis"
          :key="kpi.key"
          class="admin-kpi"
          v-bind="kpi.to ? { to: kpi.to } : {}"
        >
          <div class="admin-kpi__icon" aria-hidden="true">
            <component :is="kpi.icon" :size="18" />
          </div>
          <div class="admin-kpi__body">
            <span>{{ kpi.label }}</span>
            <strong>{{ kpi.value }}</strong>
            <small>{{ kpi.hint }}</small>
          </div>
        </component>
      </section>

      <section class="admin-panel admin-dash-revenue" :aria-busy="chartLoading ? 'true' : 'false'">
        <div class="admin-toolbar admin-dash-revenue__toolbar">
          <div>
            <h2>Sales · {{ stats.revenue_period_label }}</h2>
            <p class="admin-muted admin-dash-sub">
              {{ formatCurrency(stats.revenue_period_total) }}
              · {{ stats.revenue_period_orders }}
              {{ stats.revenue_period_orders === 1 ? 'order' : 'orders' }}
            </p>
          </div>
          <div class="admin-dash-range" role="tablist" aria-label="Sales range">
            <button
              v-for="option in revenueRanges"
              :key="option.key"
              type="button"
              role="tab"
              class="admin-dash-range__btn"
              :class="{ 'is-active': revenueRange === option.key }"
              :aria-selected="revenueRange === option.key"
              :disabled="chartLoading"
              @click="setRevenueRange(option.key)"
            >
              {{ option.label }}
            </button>
          </div>
        </div>
        <div
          class="admin-dash-bars"
          :class="{ 'is-loading': chartLoading }"
          :data-points="revenueSeries.length"
          role="img"
          :aria-label="`Revenue for ${stats.revenue_period_label}`"
        >
          <div
            v-for="(point, index) in revenueSeries"
            :key="pointKey(point, index)"
            class="admin-dash-bar"
            :title="`${point.key || point.date}: ${formatCurrency(point.total)}`"
          >
            <div class="admin-dash-bar__track">
              <div class="admin-dash-bar__fill" :style="{ height: barHeight(point.total) }" />
            </div>
            <span class="admin-dash-bar__label">{{ point.label }}</span>
            <span class="admin-dash-bar__value">{{ formatCurrency(point.total) }}</span>
          </div>
        </div>
      </section>

      <div class="admin-dash-grid admin-dash-grid--2">
        <section class="admin-panel">
          <div class="admin-toolbar">
            <h2>Recent orders</h2>
            <RouterLink class="button button--secondary button--sm" to="/admin/orders">
              View all
            </RouterLink>
          </div>
          <div v-if="stats.recent_orders.length" class="admin-table-wrap">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Order</th>
                  <th>Customer</th>
                  <th>Status</th>
                  <th>Date</th>
                  <th>Total</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="order in stats.recent_orders" :key="order.id">
                  <td data-label="Order">
                    <RouterLink :to="`/admin/orders/${order.id}`">{{ order.number }}</RouterLink>
                  </td>
                  <td data-label="Customer">
                    {{ order.address?.full_name || order.user?.name || '—' }}
                  </td>
                  <td data-label="Status">
                    <span class="admin-badge" :class="orderStatusBadgeClass(order.status)">
                      {{ orderStatusLabel(order.status) }}
                    </span>
                  </td>
                  <td data-label="Date">{{ formatDate(order.created_at) }}</td>
                  <td data-label="Total">{{ formatCurrency(order.total) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <p v-else class="admin-empty">No orders yet.</p>
        </section>

        <section class="admin-panel">
          <div class="admin-toolbar">
            <h2>Orders by status</h2>
            <RouterLink class="button button--secondary button--sm" to="/admin/orders">
              Manage
            </RouterLink>
          </div>
          <ul v-if="statusTotal" class="admin-dash-status">
            <li v-for="status in statusOrder" :key="status">
              <div class="admin-dash-status__row">
                <RouterLink
                  class="admin-dash-status__label"
                  :to="{ path: '/admin/orders', query: { status } }"
                >
                  {{ status }}
                </RouterLink>
                <strong>{{ stats.orders_by_status[status] || 0 }}</strong>
              </div>
              <div class="admin-dash-status__track" aria-hidden="true">
                <span :style="{ width: `${statusShare(status)}%` }" />
              </div>
            </li>
          </ul>
          <p v-else class="admin-empty">No order activity yet.</p>
        </section>
      </div>

      <div class="admin-dash-grid admin-dash-grid--3">
        <section class="admin-panel">
          <div class="admin-toolbar">
            <h2>Low stock</h2>
            <RouterLink class="button button--secondary button--sm" to="/admin/products">
              Catalog
            </RouterLink>
          </div>
          <ul v-if="stats.low_stock_products.length" class="admin-dash-list">
            <li v-for="product in stats.low_stock_products" :key="product.id">
              <img
                v-if="product.image"
                class="admin-dash-list__thumb"
                :src="product.image"
                :alt="product.name"
              />
              <div class="admin-dash-list__body">
                <strong>{{ product.name }}</strong>
                <span class="admin-muted">{{ product.sku || 'No SKU' }}</span>
              </div>
              <span
                class="admin-badge"
                :class="{ 'admin-badge--warn': product.stock <= 2 }"
              >
                {{ product.stock }} left
              </span>
              <RouterLink
                class="admin-dash-list__link"
                :to="{ name: 'admin-product-edit', params: { id: product.id } }"
              >
                Edit
              </RouterLink>
            </li>
          </ul>
          <p v-else class="admin-empty">All products are stocked.</p>
        </section>

        <section class="admin-panel">
          <div class="admin-toolbar">
            <h2>Recent messages</h2>
            <RouterLink class="button button--secondary button--sm" to="/admin/contacts">
              Inbox
            </RouterLink>
          </div>
          <ul v-if="stats.recent_messages.length" class="admin-dash-list admin-dash-list--simple">
            <li v-for="message in stats.recent_messages" :key="message.id">
              <div class="admin-dash-list__avatar" aria-hidden="true">
                {{ (message.name || '?').slice(0, 1).toUpperCase() }}
              </div>
              <div class="admin-dash-list__body">
                <strong>{{ message.name }}</strong>
                <span class="admin-muted">{{ snippet(message.message) }}</span>
                <span class="admin-dash-list__meta">{{ formatTime(message.created_at) }}</span>
              </div>
            </li>
          </ul>
          <p v-else class="admin-empty">No messages yet.</p>
        </section>

        <section class="admin-panel">
          <div class="admin-toolbar">
            <h2>Recent posts</h2>
            <RouterLink class="button button--secondary button--sm" to="/admin/posts">
              Blog
            </RouterLink>
          </div>
          <ul v-if="stats.recent_posts.length" class="admin-dash-list">
            <li v-for="post in stats.recent_posts" :key="post.id">
              <img
                v-if="post.cover_image"
                class="admin-dash-list__thumb"
                :src="post.cover_image"
                :alt="post.title"
              />
              <div class="admin-dash-list__body">
                <strong>{{ post.title }}</strong>
                <span class="admin-muted">{{ formatDate(post.published_at) }}</span>
              </div>
              <span class="admin-badge" :class="{ 'admin-badge--ok': isPublished(post) }">
                {{ isPublished(post) ? 'Published' : 'Draft' }}
              </span>
              <RouterLink
                class="admin-dash-list__link"
                :to="{ name: 'admin-post-edit', params: { id: post.id } }"
              >
                Edit
              </RouterLink>
            </li>
          </ul>
          <p v-else class="admin-empty">No posts yet.</p>
        </section>
      </div>
    </template>
  </div>
</template>
