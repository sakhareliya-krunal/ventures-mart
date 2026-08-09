import { blankSeoFields, buildSeoPayload, fillSeoFields, validateSeoFields } from '@/utils/adminSeo';

export function blankProductForm() {
  return {
    name: '',
    slug: '',
    sku: '',
    hsn: '',
    category_id: '',
    price: 0,
    compare_at_price: '',
    stock: 0,
    weight_kg: '',
    length_cm: '',
    breadth_cm: '',
    height_cm: '',
    is_active: true,
    images: [],
    description: '',
    badge: '',
    details: [''],
    specifications: [{ label: '', value: '' }],
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
  const details = Array.isArray(product.details) && product.details.length
    ? product.details.map(String)
    : [''];
  const specifications = Array.isArray(product.specifications) && product.specifications.length
    ? product.specifications.map((row) => ({
        label: String(row?.label ?? ''),
        value: String(row?.value ?? ''),
      }))
    : [{ label: '', value: '' }];

  Object.assign(form, {
    name: product.name || '',
    slug: product.slug || '',
    sku: product.sku || '',
    hsn: product.hsn || '',
    category_id: product.category_id || '',
    price: product.price || 0,
    compare_at_price: product.compare_at_price ?? '',
    stock: product.stock || 0,
    weight_kg: product.weight_kg ?? '',
    length_cm: product.length_cm ?? '',
    breadth_cm: product.breadth_cm ?? '',
    height_cm: product.height_cm ?? '',
    is_active: product.is_active !== false,
    images: productImagesFromRecord(product),
    description: product.description || '',
    badge: product.badge || '',
    details,
    specifications,
  });
  fillSeoFields(form, product);
}

export function buildProductPayload(form, { includeStock = true } = {}) {
  const images = (form.images || []).filter(Boolean);
  const details = (form.details || [])
    .map((item) => String(item || '').trim())
    .filter(Boolean);
  const specifications = (form.specifications || [])
    .map((row) => ({
      label: String(row?.label || '').trim(),
      value: String(row?.value || '').trim(),
    }))
    .filter((row) => row.label && row.value);

  const payload = {
    name: form.name,
    slug: form.slug || null,
    sku: form.sku,
    hsn: form.hsn || null,
    category_id: form.category_id || null,
    price: form.price,
    compare_at_price: form.compare_at_price === '' ? null : form.compare_at_price,
    weight_kg: form.weight_kg === '' ? null : form.weight_kg,
    length_cm: form.length_cm === '' ? null : form.length_cm,
    breadth_cm: form.breadth_cm === '' ? null : form.breadth_cm,
    height_cm: form.height_cm === '' ? null : form.height_cm,
    is_active: Boolean(form.is_active),
    image: images[0] || null,
    hover_image: images[1] || null,
    gallery: images.slice(1),
    description: form.description || '',
    badge: form.badge || null,
    details,
    specifications,
    ...buildSeoPayload(form),
  };

  if (includeStock) {
    payload.stock = form.stock;
  }

  return payload;
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
  if (
    form.weight_kg !== '' &&
    (Number(form.weight_kg) <= 0 || !Number.isFinite(Number(form.weight_kg)))
  ) {
    errors.weight_kg = ['Enter a positive number or leave blank to use the fallback.'];
  }
  for (const field of ['length_cm', 'breadth_cm', 'height_cm']) {
    if (form[field] !== '' && (Number(form[field]) <= 0.5 || !Number.isFinite(Number(form[field])))) {
      errors[field] = ['Enter a value greater than 0.5 cm or leave blank to use the fallback.'];
    }
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
