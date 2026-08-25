export function firstError(errors) {
  for (const value of Object.values(errors || {})) {
    if (Array.isArray(value) && value[0]) return String(value[0]);
    if (typeof value === 'string' && value) return value;
  }
  return '';
}

export function normalizeApiErrors(errors = {}) {
  return Object.fromEntries(
    Object.entries(errors || {})
      .map(([key, value]) => [key, Array.isArray(value) ? String(value[0] || '') : String(value || '')])
      .filter(([, value]) => value),
  );
}

function isBlank(value) {
  return String(value ?? '').trim() === '';
}

export const rules = {
  required(label) {
    return (value) => (isBlank(value) ? `${label} is required.` : '');
  },
  email(label = 'Email') {
    return (value) => {
      const text = String(value ?? '').trim();
      if (!text) return '';
      return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(text) ? '' : `Enter a valid ${label.toLowerCase()}.`;
    };
  },
  minLength(label, min) {
    return (value) => {
      const text = String(value ?? '');
      if (!text) return '';
      return text.length >= min ? '' : `${label} must be at least ${min} characters.`;
    };
  },
  matches(label, otherValue, otherLabel) {
    return (value) => (String(value ?? '') === String(otherValue ?? '') ? '' : `${label} must match ${otherLabel}.`);
  },
  phone(label = 'Phone') {
    return (value) => {
      const text = String(value ?? '').trim();
      if (!text) return '';
      return /^[6-9]\d{9}$/.test(text.replace(/\s+/g, '')) ? '' : `Enter a valid ${label.toLowerCase()} number.`;
    };
  },
  pincode(label = 'Postal code') {
    return (value) => {
      const text = String(value ?? '').trim();
      if (!text) return '';
      return /^\d{6}$/.test(text) ? '' : `${label} must be 6 digits.`;
    };
  },
  numberMin(label, min) {
    return (value) => {
      if (isBlank(value)) return '';
      const number = Number(value);
      return Number.isFinite(number) && number >= min ? '' : `${label} must be ${min} or more.`;
    };
  },
};

export function validateFields(form, schema) {
  const errors = {};
  for (const [field, validators] of Object.entries(schema || {})) {
    for (const validator of validators) {
      const message = validator(form[field], form);
      if (message) {
        errors[field] = message;
        break;
      }
    }
  }
  return errors;
}