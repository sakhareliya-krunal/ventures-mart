import { blankSeoFields, buildSeoPayload, fillSeoFields, validateSeoFields } from '@/utils/adminSeo';

export function blankProductForm() {
  return {
    name: '',
    slug: '',
    sku: '',
    category_id: '',
    price: 0,
    compare_at_price: '',
    stock: 0,
    is_active: true,
    images: [],
    description: '',
    badge: '',
    seo: blankSeoFields(),
    faqs: [],
    seo_score: 0,
    seo_checks: [],
    suggested_links: [],
  };
}

export function productImagesFromRecord(product) {
  const gallery = Array.isArray(product?.gallery) ? product.gallery.filter(Boolean) : [];
  if (gallery.length) return gallery;
  return product?.image ? [product.image] : [];
}

export function fillProductForm(form, product) {
  Object.assign(form, {
    name: product.name || '',
    slug: product.slug || '',
    sku: product.sku || '',
    category_id: product.category_id || '',
    price: product.price || 0,
    compare_at_price: product.compare_at_price ?? '',
    stock: product.stock || 0,
    is_active: product.is_active !== false,
    images: productImagesFromRecord(product),
    description: product.description || '',
    badge: product.badge || '',
  });
  fillSeoFields(form, product);
}

export function buildProductPayload(form) {
  const images = (form.images || []).filter(Boolean);
  return {
    name: form.name,
    slug: form.slug || null,
    sku: form.sku,
    category_id: form.category_id || null,
    price: form.price,
    compare_at_price: form.compare_at_price === '' ? null : form.compare_at_price,
    stock: form.stock,
    is_active: Boolean(form.is_active),
    image: images[0] || null,
    hover_image: images[1] || null,
    gallery: images.slice(1),
    description: form.description || '',
    badge: form.badge || null,
    ...buildSeoPayload(form),
  };
}

export function validateProductForm(form) {
  const errors = {};
  const images = (form.images || []).filter(Boolean);
  if (!String(form.name || '').trim()) errors.name = ['Name is required.'];
  if (!String(form.sku || '').trim()) errors.sku = ['SKU is required.'];
  if (!form.category_id) errors.category_id = ['Category is required.'];
  if (form.price === '' || form.price === null || Number(form.price) < 0) {
    errors.price = ['Enter a valid price.'];
  }
  if (form.stock === '' || form.stock === null || Number(form.stock) < 0) {
    errors.stock = ['Enter a valid stock quantity.'];
  }
  if (!images.length) errors.image = ['At least one product image is required.'];
  return { ...errors, ...validateSeoFields(form) };
}

export function apiErrorMessage(err, fallback) {
  return (
    err.response?.data?.message ||
    Object.values(err.response?.data?.errors || {})[0]?.[0] ||
    fallback
  );
}

export function categoryOptionsFromList(categories) {
  return (categories || []).map((category) => ({
    value: category.id,
    label: category.name,
  }));
}
