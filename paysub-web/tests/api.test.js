import test from 'node:test';
import assert from 'node:assert/strict';
import { apiFetch } from '../src/config/api.js';

test('JSON request includes the content type and bearer token', async () => {
  globalThis.fetch = async (_url, options) => {
    assert.equal(options.headers['Content-Type'], 'application/json');
    assert.equal(options.headers.Authorization, 'Bearer demo-test-token');
    assert.deepEqual(JSON.parse(options.body), { estado: 'cancelada' });
    return { ok: true, status: 200, json: async () => ({ data: { id: 1 } }) };
  };
  assert.deepEqual((await apiFetch('/test', { token: 'demo-test-token', method: 'PUT', body: { estado: 'cancelada' } })).data, { id: 1 });
});

test('multipart request preserves the file body and browser boundary', async () => {
  const form = new FormData();
  form.set('reference', 'DEMO-123');
  globalThis.fetch = async (_url, options) => {
    assert.equal(options.body, form);
    assert.equal(options.headers['Content-Type'], undefined);
    return { ok: true, status: 201, json: async () => ({ mensaje: 'Recibido' }) };
  };
  assert.equal((await apiFetch('/test', { method: 'POST', body: form })).message, 'Recibido');
});

test('field validation errors are visible to form users', async () => {
  globalThis.fetch = async () => ({ ok: false, status: 422, json: async () => ({ errors: { precio: ['Precio inválido.'] } }) });
  const result = await apiFetch('/test');
  assert.equal(result.message, 'Precio inválido.');
  assert.equal(result.ok, false);
});
