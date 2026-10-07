import test from 'node:test';
import assert from 'node:assert/strict';
import { sumPayments } from '../src/utils/support.js';

test('dashboard totals include confirmed payments in one currency only', () => {
  const payments = [
    { monto: '10.50', moneda: 'USD', estatus_pago: 'completado' },
    { monto: 500, moneda: 'VES', estatus_pago: 'completado' },
    { monto: 20, moneda: 'USD', estatus_pago: 'en_revision' },
    { monto: 30, moneda: 'USD', estatus_pago: 'simulado' },
  ];
  assert.equal(sumPayments(payments), 10.5);
  assert.equal(sumPayments(payments, 'VES'), 500);
});
