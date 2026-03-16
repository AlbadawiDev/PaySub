export const CLAIM_TYPE_OPTIONS = [
  { value: 'reclamo', label: 'Reclamo' },
  { value: 'solicitud', label: 'Solicitud' },
];

export const CLAIM_STATUS_OPTIONS = [
  { value: 'abierto', label: 'Abierto' },
  { value: 'en_revision', label: 'En revision' },
  { value: 'resuelto', label: 'Resuelto' },
  { value: 'cerrado', label: 'Cerrado' },
];

export const CLAIM_PRIORITY_OPTIONS = [
  { value: 'baja', label: 'Baja' },
  { value: 'media', label: 'Media' },
  { value: 'alta', label: 'Alta' },
];

export const CLAIM_CATEGORY_OPTIONS = [
  { value: 'general', label: 'General' },
  { value: 'pagos', label: 'Pagos' },
  { value: 'suscripciones', label: 'Suscripciones' },
  { value: 'facturacion', label: 'Facturacion' },
  { value: 'cuenta', label: 'Cuenta' },
  { value: 'soporte_tecnico', label: 'Soporte tecnico' },
  { value: 'mejora', label: 'Mejora' },
  { value: 'otro', label: 'Otro' },
];

export function unwrapData(payload) {
  return payload?.data ?? payload;
}

export function formatDate(value, options) {
  if (!value) {
    return 'Sin fecha';
  }

  return new Intl.DateTimeFormat('es-VE', options || {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(new Date(value));
}

export function formatCurrency(value, currency = 'USD') {
  const amount = Number(value || 0);

  return new Intl.NumberFormat('es-VE', {
    style: 'currency',
    currency,
    minimumFractionDigits: 2,
  }).format(amount);
}

export function getUserInitials(user) {
  const source = [user?.nombre, user?.apellido].filter(Boolean).join(' ').trim();
  if (!source) {
    return 'PS';
  }

  return source
    .split(' ')
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join('');
}

export function getClaimStatusLabel(status) {
  return CLAIM_STATUS_OPTIONS.find((item) => item.value === status)?.label || 'Sin estado';
}

export function getClaimTypeLabel(type) {
  return CLAIM_TYPE_OPTIONS.find((item) => item.value === type)?.label || 'Caso';
}

export function getClaimPriorityLabel(priority) {
  return CLAIM_PRIORITY_OPTIONS.find((item) => item.value === priority)?.label || 'Media';
}

export function getClaimTone(status) {
  switch (status) {
    case 'abierto':
      return 'danger';
    case 'en_revision':
      return 'warning';
    case 'resuelto':
      return 'success';
    case 'cerrado':
      return 'neutral';
    default:
      return 'neutral';
  }
}

export function getClaimContextLabel(claim) {
  if (claim?.pago) {
    return `Pago ${claim.pago.referencia_operacion || `#${claim.pago.id_pago}`}`;
  }

  if (claim?.suscripcion?.plan?.nombre_plan) {
    return claim.suscripcion.plan.nombre_plan;
  }

  return 'Caso general';
}

export function sumPayments(items = []) {
  return items.reduce((accumulator, item) => accumulator + Number(item?.monto || 0), 0);
}
