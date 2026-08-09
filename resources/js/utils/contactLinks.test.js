import { describe, expect, it } from 'vitest';
import { emailHref, phoneHref } from './contactLinks';

describe('contact link helpers', () => {
  it('builds an email link from a trimmed address', () => {
    expect(emailHref('  help@example.com  ')).toBe('mailto:help@example.com');
  });

  it('normalizes a formatted international phone number', () => {
    expect(phoneHref('+91 91732 79323')).toBe('tel:+919173279323');
  });

  it('does not build links for empty contact values', () => {
    expect(emailHref('  ')).toBe('');
    expect(phoneHref(null)).toBe('');
  });
});
