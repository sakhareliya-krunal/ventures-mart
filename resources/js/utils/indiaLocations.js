import { getAllStates, getDistricts } from 'india-state-district';

const states = getAllStates();
const stateByName = new Map(states.map((state) => [state.name.toLowerCase(), state]));
const stateByCode = new Map(states.map((state) => [state.code.toLowerCase(), state]));

export const indiaStateOptions = states
  .map((state) => ({ value: state.name, label: state.name }))
  .sort((left, right) => left.label.localeCompare(right.label));

export function districtOptionsForState(stateValue) {
  const lookup = String(stateValue ?? '').trim().toLowerCase();
  const state = stateByName.get(lookup) || stateByCode.get(lookup);

  if (!state) {
    return [];
  }

  return getDistricts(state.code)
    .map((district) => ({ value: district, label: district }))
    .sort((left, right) => left.label.localeCompare(right.label));
}

export function isDistrictInState(stateValue, districtValue) {
  const district = String(districtValue ?? '').trim().toLowerCase();

  return districtOptionsForState(stateValue).some(
    (option) => option.value.toLowerCase() === district,
  );
}

export function cityForDistrictChange(currentCity, nextDistrict, previousDistrict = '') {
  const city = String(currentCity ?? '').trim();
  const next = String(nextDistrict ?? '').trim();
  const previous = String(previousDistrict ?? '').trim();
  const cityWasAutofilled =
    previous && city.localeCompare(previous, undefined, { sensitivity: 'accent' }) === 0;

  return !city || cityWasAutofilled ? next : currentCity;
}
