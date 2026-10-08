export const API_BASE_URL = (import.meta.env?.VITE_API_BASE_URL || 'http://127.0.0.1:8012/api').replace(/\/$/, '');

export function getAuthHeaders(token, extraHeaders = {}) {
  return {
    Accept: 'application/json',
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
    ...extraHeaders,
  };
}

export async function apiFetch(path, { token, method = 'GET', body, headers = {} } = {}) {
  const isForm = typeof FormData !== 'undefined' && body instanceof FormData;
  const isJson = body !== undefined && typeof body !== 'string' && !isForm;
  const response = await fetch(`${API_BASE_URL}${path}`, {
    method,
    headers: getAuthHeaders(token, { ...(isJson ? { 'Content-Type': 'application/json' } : {}), ...headers }),
    ...(body !== undefined ? { body: isJson ? JSON.stringify(body) : body } : {}),
  });

  const payload = await response.json().catch(() => ({}));

  return {
    ok: response.ok,
    status: response.status,
    payload,
    data: payload?.data ?? payload,
    message: payload?.mensaje || payload?.message || payload?.error ||
      (payload?.errors ? Object.values(payload.errors).flat().join(' ') : undefined),
  };
}
