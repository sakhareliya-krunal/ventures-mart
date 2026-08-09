import { describe, expect, it } from 'vitest';
import {
  cityForDistrictChange,
  districtOptionsForState,
  indiaStateOptions,
  isDistrictInState,
} from './indiaLocations';

describe('India location options', () => {
  it('exposes canonical state names and dependent districts', () => {
    expect(indiaStateOptions).toContainEqual({ value: 'Gujarat', label: 'Gujarat' });
    expect(districtOptionsForState('Gujarat')).toContainEqual({
      value: 'Ahmedabad',
      label: 'Ahmedabad',
    });
  });

  it('supports state codes and rejects mismatched districts', () => {
    expect(isDistrictInState('GJ', 'Ahmedabad')).toBe(true);
    expect(isDistrictInState('Gujarat', 'Mumbai')).toBe(false);
    expect(districtOptionsForState('')).toEqual([]);
  });

  it('autofills city from district without overwriting manual values', () => {
    expect(cityForDistrictChange('', 'Surat')).toBe('Surat');
    expect(cityForDistrictChange('Surat', 'Ahmedabad', 'Surat')).toBe('Ahmedabad');
    expect(cityForDistrictChange('Bardoli', 'Ahmedabad', 'Surat')).toBe('Bardoli');
  });
});
