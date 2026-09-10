import { mount } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';
import { describe, expect, it, vi } from 'vitest';
import AdminUserCreatePage from './AdminUserCreatePage.vue';

vi.mock('@/services/api', () => ({
  default: { post: vi.fn() },
}));

async function mountPage() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/admin/users/create', name: 'admin-user-create', component: AdminUserCreatePage }],
  });
  await router.push({ name: 'admin-user-create' });
  await router.isReady();

  return mount(AdminUserCreatePage, {
    global: {
      plugins: [router],
      stubs: {
        FormField: {
          props: ['modelValue', 'label'],
          emits: ['update:modelValue'],
          template: '<label>{{ label }}<input /></label>',
        },
        AppButton: {
          props: ['type', 'form', 'disabled', 'loading'],
          template: '<button :type="type || \'button\'" :form="form" :disabled="disabled"><slot /></button>',
        },
      },
    },
  });
}

describe('AdminUserCreatePage', () => {
  it('links footer submit to the form id and keeps actions in the panel footer', async () => {
    const wrapper = await mountPage();

    expect(wrapper.find('#admin-user-create-form').exists()).toBe(true);
    expect(wrapper.find('.admin-panel--form').exists()).toBe(true);

    const submit = wrapper.find('.admin-panel__footer button[type="submit"]');
    expect(submit.exists()).toBe(true);
    expect(submit.attributes('form')).toBe('admin-user-create-form');
    expect(wrapper.find('.admin-form-actions--footer').exists()).toBe(true);
  });
});
