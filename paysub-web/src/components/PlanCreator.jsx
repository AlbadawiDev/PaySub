import { useState } from 'react';
import { apiFetch } from '../config/api';

export default function PlanCreator({ token, onCreated }) {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  async function submit(event) {
    event.preventDefault();
    const form = event.currentTarget;
    setBusy(true);
    setError('');
    try {
      const response = await apiFetch('/planes', {
        token, method: 'POST', body: Object.fromEntries(new FormData(form)),
      });
      if (!response.ok) throw new Error(response.message || 'No se pudo crear el plan.');
      onCreated(response.data);
      form.reset();
    } catch (failure) {
      setError(failure.message);
    } finally {
      setBusy(false);
    }
  }

  return (
    <form className="workspace-form" onSubmit={submit} style={{ marginBottom: 24 }}>
      <h3>Publicar un plan</h3>
      <p className="workspace-subtle">Define el servicio y la frecuencia. Los pagos móviles requieren revisión.</p>
      <div className="workspace-form__grid">
        <label>Nombre del plan<input name="nombre_plan" required maxLength={100} /></label>
        <label>Precio<input name="precio" type="number" required min="0" step="0.01" /></label>
        <label>Moneda<select name="moneda"><option>USD</option><option>VES</option><option>EUR</option></select></label>
        <label>Frecuencia<select name="frecuencia"><option value="mensual">Mensual</option><option value="semanal">Semanal</option><option value="trimestral">Trimestral</option><option value="anual">Anual</option></select></label>
      </div>
      <label>Descripción<textarea name="descripcion" maxLength={1000} /></label>
      <input type="hidden" name="modalidad_cobro" value="prepago" />
      {error && <p role="alert">{error}</p>}
      <button className="workspace-button" disabled={busy}>{busy ? 'Publicando…' : 'Publicar plan'}</button>
    </form>
  );
}
