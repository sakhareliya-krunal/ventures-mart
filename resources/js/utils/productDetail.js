import { productTabLabels } from '@/constants/product';

const MATERIAL_LABEL_PATTERN = /material|compartment|product type|colour|color|best for|pack style/i;
const CARE_LABEL_PATTERN = /care|clean|wash|delivery|replacement|fulfilment|fulfillment/i;

function normalizeSpecs(specifications = []) {
  return (specifications || []).filter((row) => row?.label && row?.value);
}

function normalizeBullets(items = []) {
  return (items || []).filter((item) => typeof item === 'string' && item.trim()).map((item) => item.trim());
}

function specRowsUsedElsewhere(specs, usedLabels) {
  return specs.filter((row) => !usedLabels.has(row.label.toLowerCase()));
}

export function productSizeLabel(product) {
  if (!product) {
    return null;
  }

  const sizeSpec = normalizeSpecs(product.specifications).find((row) => /^size$/i.test(row.label));
  if (sizeSpec) {
    return sizeSpec.value;
  }

  const dimensions = [product.height_cm, product.length_cm, product.breadth_cm]
    .map((value) => Number(value))
    .filter((value) => Number.isFinite(value) && value > 0);

  if (!dimensions.length) {
    return null;
  }

  const largest = Math.max(...dimensions);
  const formatted = Number.isInteger(largest) ? String(largest) : largest.toFixed(1);
  return `${formatted} CM`;
}

export function productRatingLabel(product) {
  const rating = Number(product?.rating ?? 0).toFixed(1);
  const count = Number(product?.reviews ?? 0);
  const reviewWord = count === 1 ? 'customer review' : 'customer reviews';
  return `${rating} / 5.0 (${count} ${reviewWord})`;
}

export function productBackTarget(product) {
  if (product?.category) {
    return {
      to: `/category/${product.category}`,
      label: product.category_name || product.category,
    };
  }

  return {
    to: '/shop',
    label: null,
  };
}

export function buildProductTabs(product) {
  if (!product) {
    return [];
  }

  const specs = normalizeSpecs(product.specifications);
  const details = normalizeBullets(product.details);
  const highlights = normalizeBullets(product.seo?.metadata?.ai_highlights);

  const materialSpecs = specs.filter((row) => MATERIAL_LABEL_PATTERN.test(row.label));
  const careSpecs = specs.filter((row) => CARE_LABEL_PATTERN.test(row.label));
  const usedLabels = new Set([
    ...materialSpecs.map((row) => row.label.toLowerCase()),
    ...careSpecs.map((row) => row.label.toLowerCase()),
  ]);

  const remainingSpecs = specRowsUsedElsewhere(specs, usedLabels);
  const featureBullets = [...highlights, ...details.filter((item) => !highlights.includes(item))];

  const tabs = [
    {
      id: 'materials',
      label: productTabLabels.materials,
      specs: materialSpecs,
      bullets: materialSpecs.length ? [] : details.slice(0, Math.ceil(details.length / 2)),
    },
    {
      id: 'care',
      label: productTabLabels.care,
      specs: careSpecs,
      bullets: [],
    },
    {
      id: 'highlights',
      label: productTabLabels.highlights,
      specs: remainingSpecs,
      bullets: featureBullets,
    },
  ];

  return tabs.filter((tab) => tab.specs.length || tab.bullets.length);
}
