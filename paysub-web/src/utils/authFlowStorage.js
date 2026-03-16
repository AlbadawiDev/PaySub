const PENDING_REGISTRATION_KEY = 'paysub_pending_registration';
const VERIFIED_REGISTRATION_KEY = 'paysub_verified_registration';
const LOGIN_PREFILL_EMAIL_KEY = 'paysub_login_prefill_email';

export const AUTH_FLOW_STORAGE_KEYS = {
  pending: PENDING_REGISTRATION_KEY,
  verified: VERIFIED_REGISTRATION_KEY,
  loginPrefill: LOGIN_PREFILL_EMAIL_KEY,
};

export function loadPendingRegistration() {
  try {
    const raw = sessionStorage.getItem(PENDING_REGISTRATION_KEY);
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
}

export function savePendingRegistration(payload) {
  sessionStorage.setItem(PENDING_REGISTRATION_KEY, JSON.stringify(payload));
}

export function clearPendingRegistration() {
  sessionStorage.removeItem(PENDING_REGISTRATION_KEY);
}

export function saveVerifiedRegistration(email) {
  sessionStorage.setItem(VERIFIED_REGISTRATION_KEY, email);
}

export function consumeVerifiedRegistration() {
  const email = sessionStorage.getItem(VERIFIED_REGISTRATION_KEY);
  if (email) {
    sessionStorage.removeItem(VERIFIED_REGISTRATION_KEY);
  }

  return email;
}

export function saveLoginPrefill(email) {
  sessionStorage.setItem(LOGIN_PREFILL_EMAIL_KEY, email);
}

export function consumeLoginPrefill() {
  const email = sessionStorage.getItem(LOGIN_PREFILL_EMAIL_KEY);
  if (email) {
    sessionStorage.removeItem(LOGIN_PREFILL_EMAIL_KEY);
  }

  return email;
}
