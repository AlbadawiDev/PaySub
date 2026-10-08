import { useEffect, useState } from 'react';
import { apiFetch } from '../config/api';
import { formatCurrency } from '../utils/support';

export default function MobilePaymentForm({ token, plans, onCreated }) {
  const [planId, setPlanId] = useState('');
  const [recipient, setRecipient] = useState(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    let active = true;
    if (!planId) return;
    apiFetch(`/planes/${planId}`, { token }).then((response) => {
      if (active) setRecipient(response.ok ? response.data.comercio?.datos_pago?.find((item) => item.activo) : null);
    }).catch(() => { if (active) setRecipient(null); });
    return () => { active = false; };
  }, [planId, token]);

  async function submit(event) {
    event.preventDefault();
    const form = event.currentTarget;
    setBusy(true);
    setError('');
    try {
      const body = new FormData(form);
      body.set('metodo_usado', 'pago_movil');
      const response = await apiFetch('/suscripciones', { token, method: 'POST', body });
      if (!response.ok) throw new Error(response.message || 'No se pudo reportar el pago.');
      onCreated(response.data, response.message);
      form.reset();
      setPlanId('');
      setRecipient(null);
    } catch (failure) {
      setError(failure.message);
    } finally {
      setBusy(false);
    }
  }

  return (
    <form className="workspace-form" onSubmit={submit} style={{ marginBottom: 24 }}>
      <h3>Suscribirse mediante reporte de pago</h3>
      <p className="workspace-subtle">El comercio revisará el comprobante antes de activar tu suscripción. La demostración usa datos ficticios.</p>
      <label>Seleccionar plan<select name="id_plan" required value={planId} onChange={(event) => { setRecipient(null); setPlanId(event.target.value); }}>
        <option value="">Selecciona un servicio</option>
        {plans.filter((plan) => plan.estado).map((plan) => <option key={plan.id_plan} value={plan.id_plan}>{plan.nombre_plan} · {formatCurrency(plan.precio, plan.moneda)}</option>)}
      </select></label>
      {planId && <p role="status">{recipient ? `Destino: ${recipient.banco} · ${recipient.titular} · ${recipient.telefono_pago} · ${recipient.rif_cedula}` : 'El comercio aún no tiene datos de cobro disponibles.'}</p>}
      <div className="workspace-form__grid">
        <label>Referencia<input name="referencia_operacion" required maxLength={100} /></label>
        <label>Banco remitente<input name="banco_remitente" required maxLength={100} /></label>
        <label>Teléfono remitente<input name="telefono_remitente" required maxLength={20} /></label>
        <label>Fecha del pago<input name="fecha_pago" type="date" required /></label>
        <label>Comprobante privado<input name="comprobante" type="file" accept="image/png,image/jpeg,image/webp" required /></label>
      </div>
      {error && <p role="alert">{error}</p>}
      <button className="workspace-button" disabled={busy || !recipient}>{busy ? 'Enviando…' : 'Reportar pago y solicitar suscripción'}</button>
    </form>
  );
}
