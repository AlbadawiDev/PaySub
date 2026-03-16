import React, { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { apiFetch, API_BASE_URL, getAuthHeaders } from '../config/api';
import WorkspaceLayout, {
  WorkspaceBanner,
  WorkspaceMetricCard,
  WorkspacePill,
  WorkspaceState,
} from './shared/WorkspaceLayout';
import {
  CLAIM_CATEGORY_OPTIONS,
  CLAIM_PRIORITY_OPTIONS,
  CLAIM_STATUS_OPTIONS,
  CLAIM_TYPE_OPTIONS,
  formatCurrency,
  formatDate,
  getClaimContextLabel,
  getClaimPriorityLabel,
  getClaimStatusLabel,
  getClaimTone,
  getClaimTypeLabel,
  sumPayments,
  unwrapData,
} from '../utils/support';

const INITIAL_CLAIM_FORM = {
  tipo: 'reclamo',
  categoria: 'general',
  prioridad: 'media',
  asunto: '',
  descripcion: '',
  id_suscripcion: '',
  id_pago: '',
};

const sectionOptions = [
  { key: 'overview', label: 'Resumen', caption: 'Estado general de tu cuenta' },
  { key: 'claims', label: 'Reclamos', caption: 'Soporte y solicitudes' },
  { key: 'subscriptions', label: 'Suscripciones', caption: 'Control de renovaciones' },
  { key: 'payments', label: 'Pagos', caption: 'Historial y montos' },
  { key: 'plans', label: 'Explorar planes', caption: 'Catalogo disponible' },
];

const ClienteDashboard = () => {
  const navigate = useNavigate();
  const token = localStorage.getItem('access_token');

  const [activeSection, setActiveSection] = useState('overview');
  const [user, setUser] = useState(null);
  const [suscripciones, setSuscripciones] = useState([]);
  const [pagos, setPagos] = useState([]);
  const [planes, setPlanes] = useState([]);
  const [claims, setClaims] = useState([]);
  const [selectedClaimId, setSelectedClaimId] = useState(null);
  const [selectedClaim, setSelectedClaim] = useState(null);
  const [claimForm, setClaimForm] = useState(INITIAL_CLAIM_FORM);
  const [claimFilter, setClaimFilter] = useState('todos');
  const [claimSearch, setClaimSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const [claimDetailLoading, setClaimDetailLoading] = useState(false);
  const [submittingClaim, setSubmittingClaim] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  useEffect(() => {
    if (!token) {
      navigate('/login');
      return;
    }

    const loadDashboard = async () => {
      setLoading(true);
      setError('');

      try {
        const [userRes, susRes, pagosRes, planesRes, claimsRes] = await Promise.all([
          apiFetch('/user', { token }),
          apiFetch('/mis-suscripciones', { token }),
          apiFetch('/pagos', { token }),
          apiFetch('/planes', { token }),
          apiFetch('/reclamos', { token }),
        ]);

        if (userRes.status === 401) {
          localStorage.clear();
          navigate('/login');
          return;
        }

        if (!userRes.ok) {
          throw new Error(userRes.message || 'No pudimos recuperar tu sesion.');
        }

        if (!susRes.ok || !pagosRes.ok || !planesRes.ok || !claimsRes.ok) {
          throw new Error(
            susRes.message ||
              pagosRes.message ||
              planesRes.message ||
              claimsRes.message ||
              'No fue posible cargar todos los datos del panel.'
          );
        }

        const userPayload = unwrapData(userRes.payload);
        const claimsPayload = Array.isArray(unwrapData(claimsRes.payload)) ? unwrapData(claimsRes.payload) : [];

        setUser(userPayload);
        setSuscripciones(Array.isArray(unwrapData(susRes.payload)) ? unwrapData(susRes.payload) : []);
        setPagos(Array.isArray(unwrapData(pagosRes.payload)) ? unwrapData(pagosRes.payload) : []);
        setPlanes(Array.isArray(unwrapData(planesRes.payload)) ? unwrapData(planesRes.payload) : []);
        setClaims(claimsPayload);

        if (claimsPayload[0]?.id_reclamo) {
          setSelectedClaimId(claimsPayload[0].id_reclamo);
        }
      } catch (fetchError) {
        setError(fetchError.message || 'Ocurrio un error cargando tu panel.');
      } finally {
        setLoading(false);
      }
    };

    loadDashboard();
  }, [navigate, token]);

  useEffect(() => {
    if (!selectedClaimId || !token) {
      setSelectedClaim(null);
      return;
    }

    const loadClaimDetail = async () => {
      setClaimDetailLoading(true);

      try {
        const response = await apiFetch(`/reclamos/${selectedClaimId}`, { token });

        if (!response.ok) {
          throw new Error(response.message || 'No fue posible abrir el detalle del caso.');
        }

        setSelectedClaim(unwrapData(response.payload));
      } catch (detailError) {
        setSelectedClaim(null);
        setError(detailError.message);
      } finally {
        setClaimDetailLoading(false);
      }
    };

    loadClaimDetail();
  }, [selectedClaimId, token]);

  const handleLogout = async () => {
    try {
      await fetch(`${API_BASE_URL}/logout`, {
        method: 'POST',
        headers: getAuthHeaders(token),
      });
    } catch {
      // noop
    } finally {
      localStorage.clear();
      navigate('/login');
    }
  };

  const cancelarSuscripcion = async (idSuscripcion) => {
    setSuccess('');
    setError('');

    try {
      const response = await fetch(`${API_BASE_URL}/suscripciones/${idSuscripcion}/estado`, {
        method: 'PUT',
        headers: getAuthHeaders(token, { 'Content-Type': 'application/json' }),
        body: JSON.stringify({ estado: 'cancelada' }),
      });

      const payload = await response.json().catch(() => ({}));

      if (!response.ok) {
        throw new Error(payload?.error || payload?.message || payload?.mensaje || 'No se pudo cancelar la suscripcion.');
      }

      setSuscripciones((current) =>
        current.map((item) =>
          item.id_suscripcion === idSuscripcion ? { ...item, estado: 'cancelada' } : item
        )
      );
      setSuccess('Suscripcion cancelada exitosamente.');
    } catch (cancelError) {
      setError(cancelError.message);
    }
  };

  const handleClaimFormChange = (event) => {
    const { name, value } = event.target;
    setClaimForm((current) => ({
      ...current,
      [name]: value,
    }));
  };

  const submitClaim = async (event) => {
    event.preventDefault();
    setSubmittingClaim(true);
    setError('');
    setSuccess('');

    try {
      const response = await apiFetch('/reclamos', {
        token,
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: {
          ...claimForm,
          id_suscripcion: claimForm.id_suscripcion || null,
          id_pago: claimForm.id_pago || null,
        },
      });

      if (!response.ok) {
        throw new Error(response.message || 'No fue posible registrar el caso.');
      }

      const createdClaim = unwrapData(response.payload);

      setClaims((current) => [createdClaim, ...current]);
      setSelectedClaimId(createdClaim.id_reclamo);
      setClaimForm(INITIAL_CLAIM_FORM);
      setActiveSection('claims');
      setSuccess('Tu caso fue creado y ya esta visible en el historial.');
    } catch (submitError) {
      setError(submitError.message);
    } finally {
      setSubmittingClaim(false);
    }
  };

  const filteredClaims = useMemo(() => {
    return claims.filter((claim) => {
      const matchesStatus = claimFilter === 'todos' ? true : claim.estado === claimFilter;
      const search = claimSearch.trim().toLowerCase();
      const matchesSearch = search
        ? [claim.codigo, claim.asunto, claim.descripcion].some((value) =>
            String(value || '').toLowerCase().includes(search)
          )
        : true;

      return matchesStatus && matchesSearch;
    });
  }, [claimFilter, claimSearch, claims]);

  const pagosRegistrados = pagos.length;
  const suscripcionesActivas = suscripciones.filter((item) => item.estado === 'activa');
  const claimsOpen = claims.filter((claim) => ['abierto', 'en_revision'].includes(claim.estado));
  const latestClaim = claims[0];
  const latestPayment = pagos[0];

  if (loading) {
    return (
      <div className="workspace-shell">
        <div className="workspace-layout">
          <div className="workspace-main">
            <WorkspaceState
              title="Cargando panel de cliente"
              message="Estamos recuperando tus suscripciones, pagos y reclamos para mostrar un resumen actualizado."
            />
          </div>
        </div>
      </div>
    );
  }

  if (!user) {
    return (
      <div className="workspace-shell">
        <div className="workspace-layout">
          <div className="workspace-main">
            <WorkspaceState
              title="No pudimos cargar tu cuenta"
              message="Inicia sesion nuevamente para reconstruir el panel."
              actionLabel="Volver al login"
              onAction={() => navigate('/login')}
            />
          </div>
        </div>
      </div>
    );
  }

  return (
    <WorkspaceLayout
      brand="PaySub"
      productLabel="Panel cliente"
      user={user}
      sections={sectionOptions}
      activeSection={activeSection}
      onSectionChange={setActiveSection}
      onLogout={handleLogout}
      title="Tu operacion en un solo lugar"
      subtitle="Controla suscripciones, revisa cobros y registra reclamos o solicitudes con seguimiento claro."
      banner={
        error
          ? { tone: 'error', title: 'Algo requiere atencion', message: error }
          : success
            ? { tone: 'success', title: 'Operacion completada', message: success }
            : null
      }
    >
      {activeSection === 'overview' && (
        <section>
          <div className="workspace-section__header">
            <div>
              <h2>Resumen ejecutivo</h2>
              <p>Una vista compacta de tu actividad reciente y del estado de soporte.</p>
            </div>
          </div>

          <div className="workspace-grid workspace-grid--metrics">
            <WorkspaceMetricCard
              label="Suscripciones activas"
              value={suscripcionesActivas.length}
              hint="Servicios que siguen vigentes"
            />
            <WorkspaceMetricCard
              label="Pagos registrados"
              value={pagosRegistrados}
              hint="Cobros historicos asociados a tu cuenta"
            />
            <WorkspaceMetricCard
              label="Casos abiertos"
              value={claimsOpen.length}
              hint="Reclamos o solicitudes en seguimiento"
              tone={claimsOpen.length > 0 ? 'warning' : 'success'}
            />
            <WorkspaceMetricCard
              label="Ultimo cobro"
              value={latestPayment ? formatDate(latestPayment.created_at) : 'Sin datos'}
              hint={latestPayment ? formatCurrency(latestPayment.monto, latestPayment.moneda) : 'Aun no hay pagos'}
            />
          </div>

          <div className="workspace-grid workspace-grid--split" style={{ marginTop: 18 }}>
            <article className="workspace-card">
              <div className="workspace-card__header">
                <div>
                  <h3>Actividad de soporte</h3>
                  <p>Tu caso mas reciente y lo que viene luego.</p>
                </div>
                {latestClaim && (
                  <WorkspacePill label={getClaimStatusLabel(latestClaim.estado)} tone={getClaimTone(latestClaim.estado)} />
                )}
              </div>

              {latestClaim ? (
                <div className="workspace-stack">
                  <div>
                    <strong>{latestClaim.asunto}</strong>
                    <p className="workspace-subtle" style={{ marginTop: 8 }}>
                      {latestClaim.codigo} · {getClaimTypeLabel(latestClaim.tipo)} · {formatDate(latestClaim.created_at)}
                    </p>
                  </div>
                  <p className="workspace-subtle">{latestClaim.descripcion}</p>
                  <div className="workspace-kpis">
                    <WorkspacePill label={getClaimPriorityLabel(latestClaim.prioridad)} tone="neutral" />
                    <WorkspacePill label={getClaimContextLabel(latestClaim)} tone="neutral" />
                  </div>
                </div>
              ) : (
                <WorkspaceState
                  title="Sin casos registrados"
                  message="Cuando necesites reportar un problema o dejar una solicitud, podras hacerlo desde la seccion de reclamos."
                  actionLabel="Abrir centro de reclamos"
                  onAction={() => setActiveSection('claims')}
                />
              )}
            </article>

            <article className="workspace-card">
              <div className="workspace-card__header">
                <div>
                  <h3>Ingresos procesados</h3>
                  <p>Total acumulado en tus pagos confirmados.</p>
                </div>
              </div>

              <strong style={{ fontSize: '2.3rem', display: 'block' }}>{formatCurrency(sumPayments(pagos), 'USD')}</strong>
              <p className="workspace-subtle" style={{ marginTop: 10 }}>
                {suscripcionesActivas.length > 0
                  ? `Tienes ${suscripcionesActivas.length} suscripcion${suscripcionesActivas.length === 1 ? '' : 'es'} activa${suscripcionesActivas.length === 1 ? '' : 's'}.`
                  : 'No hay servicios activos en este momento.'}
              </p>
            </article>
          </div>
        </section>
      )}

      {activeSection === 'claims' && (
        <section>
          <div className="workspace-section__header">
            <div>
              <h2>Centro de reclamos y solicitudes</h2>
              <p>Registra un caso, filtra tu historial y consulta el detalle con respuesta administrativa.</p>
            </div>
          </div>

          <div className="workspace-split">
            <div className="workspace-stack">
              <form className="workspace-form" onSubmit={submitClaim}>
                <div className="workspace-card__header">
                  <div>
                    <h3>Nuevo caso</h3>
                    <p>Describe el problema o la solicitud con suficiente contexto.</p>
                  </div>
                </div>

                <div className="workspace-form__grid">
                  <label>
                    Tipo
                    <select name="tipo" value={claimForm.tipo} onChange={handleClaimFormChange}>
                      {CLAIM_TYPE_OPTIONS.map((option) => (
                        <option key={option.value} value={option.value}>{option.label}</option>
                      ))}
                    </select>
                  </label>

                  <label>
                    Categoria
                    <select name="categoria" value={claimForm.categoria} onChange={handleClaimFormChange}>
                      {CLAIM_CATEGORY_OPTIONS.map((option) => (
                        <option key={option.value} value={option.value}>{option.label}</option>
                      ))}
                    </select>
                  </label>

                  <label>
                    Prioridad
                    <select name="prioridad" value={claimForm.prioridad} onChange={handleClaimFormChange}>
                      {CLAIM_PRIORITY_OPTIONS.map((option) => (
                        <option key={option.value} value={option.value}>{option.label}</option>
                      ))}
                    </select>
                  </label>

                  <label>
                    Suscripcion asociada
                    <select name="id_suscripcion" value={claimForm.id_suscripcion} onChange={handleClaimFormChange}>
                      <option value="">Sin asociar</option>
                      {suscripciones.map((item) => (
                        <option key={item.id_suscripcion} value={item.id_suscripcion}>
                          {item.plan?.nombre_plan || `Suscripcion #${item.id_suscripcion}`}
                        </option>
                      ))}
                    </select>
                  </label>

                  <label>
                    Pago asociado
                    <select name="id_pago" value={claimForm.id_pago} onChange={handleClaimFormChange}>
                      <option value="">Sin asociar</option>
                      {pagos.map((item) => (
                        <option key={item.id_pago} value={item.id_pago}>
                          {formatDate(item.created_at)} · {formatCurrency(item.monto, item.moneda)}
                        </option>
                      ))}
                    </select>
                  </label>

                  <label>
                    Asunto
                    <input
                      type="text"
                      name="asunto"
                      value={claimForm.asunto}
                      onChange={handleClaimFormChange}
                      placeholder="Ej. Cobro duplicado o solicitud de ajuste"
                      maxLength={160}
                      required
                    />
                  </label>
                </div>

                <label style={{ marginTop: 14 }}>
                  Descripcion
                  <textarea
                    name="descripcion"
                    value={claimForm.descripcion}
                    onChange={handleClaimFormChange}
                    placeholder="Explica lo ocurrido, fechas, referencia o cualquier detalle relevante."
                    minLength={20}
                    required
                  />
                </label>

                <div className="workspace-form__actions">
                  <button
                    type="button"
                    className="workspace-button workspace-button--ghost"
                    onClick={() => setClaimForm(INITIAL_CLAIM_FORM)}
                  >
                    Limpiar
                  </button>
                  <button type="submit" className="workspace-button workspace-button--primary" disabled={submittingClaim}>
                    {submittingClaim ? 'Registrando...' : 'Crear caso'}
                  </button>
                </div>
              </form>

              <article className="workspace-card">
                <div className="workspace-card__header">
                  <div>
                    <h3>Historial de casos</h3>
                    <p>Filtra y revisa tus reclamos o solicitudes registrados.</p>
                  </div>
                </div>

                <div className="workspace-form__grid" style={{ marginBottom: 16 }}>
                  <label>
                    Estado
                    <select value={claimFilter} onChange={(event) => setClaimFilter(event.target.value)}>
                      <option value="todos">Todos</option>
                      {CLAIM_STATUS_OPTIONS.map((option) => (
                        <option key={option.value} value={option.value}>{option.label}</option>
                      ))}
                    </select>
                  </label>

                  <label>
                    Buscar
                    <input
                      type="text"
                      value={claimSearch}
                      onChange={(event) => setClaimSearch(event.target.value)}
                      placeholder="Codigo, asunto o descripcion"
                    />
                  </label>
                </div>

                {filteredClaims.length === 0 ? (
                  <WorkspaceState
                    title="No hay casos para este filtro"
                    message="Ajusta la busqueda o registra un nuevo reclamo desde el formulario."
                  />
                ) : (
                  <div className="workspace-list">
                    {filteredClaims.map((claim) => (
                      <button
                        key={claim.id_reclamo}
                        type="button"
                        className={`workspace-list__item ${selectedClaimId === claim.id_reclamo ? 'is-active' : ''}`}
                        onClick={() => setSelectedClaimId(claim.id_reclamo)}
                      >
                        <strong>{claim.asunto}</strong>
                        <span style={{ marginTop: 8 }}>{claim.codigo}</span>
                        <small>{getClaimTypeLabel(claim.tipo)} · {formatDate(claim.created_at)}</small>
                        <div className="workspace-kpis" style={{ marginTop: 12 }}>
                          <WorkspacePill label={getClaimStatusLabel(claim.estado)} tone={getClaimTone(claim.estado)} />
                          <WorkspacePill label={getClaimPriorityLabel(claim.prioridad)} tone="neutral" />
                        </div>
                      </button>
                    ))}
                  </div>
                )}
              </article>
            </div>

            <div className="workspace-stack">
              {claimDetailLoading ? (
                <WorkspaceState
                  title="Cargando detalle"
                  message="Estamos recuperando la informacion completa del caso seleccionado."
                />
              ) : selectedClaim ? (
                <article className="workspace-detail">
                  <div className="workspace-card__header">
                    <div>
                      <h3>{selectedClaim.asunto}</h3>
                      <p>{selectedClaim.codigo} · {formatDate(selectedClaim.created_at)}</p>
                    </div>
                    <WorkspacePill
                      label={getClaimStatusLabel(selectedClaim.estado)}
                      tone={getClaimTone(selectedClaim.estado)}
                    />
                  </div>

                  <div className="workspace-detail__meta" style={{ marginBottom: 16 }}>
                    <WorkspacePill label={getClaimTypeLabel(selectedClaim.tipo)} tone="neutral" />
                    <WorkspacePill label={getClaimPriorityLabel(selectedClaim.prioridad)} tone="neutral" />
                    <WorkspacePill label={getClaimContextLabel(selectedClaim)} tone="neutral" />
                  </div>

                  <p className="workspace-subtle" style={{ lineHeight: 1.7 }}>{selectedClaim.descripcion}</p>

                  <div className="workspace-card" style={{ marginTop: 18 }}>
                    <div className="workspace-card__header">
                      <div>
                        <h3>Respuesta administrativa</h3>
                        <p>Seguimiento visible para el cliente.</p>
                      </div>
                    </div>
                    {selectedClaim.respuesta_admin ? (
                      <p className="workspace-subtle" style={{ lineHeight: 1.7 }}>{selectedClaim.respuesta_admin}</p>
                    ) : (
                      <WorkspaceBanner
                        tone="warning"
                        title="Aun sin respuesta"
                        message="Tu caso ya fue recibido, pero todavia no tiene una nota administrativa cargada."
                      />
                    )}
                  </div>

                  <div className="workspace-card" style={{ marginTop: 18 }}>
                    <div className="workspace-card__header">
                      <div>
                        <h3>Contexto del caso</h3>
                        <p>Informacion vinculada si el reclamo fue asociado a un pago o suscripcion.</p>
                      </div>
                    </div>
                    <div className="workspace-stack">
                      <span className="workspace-subtle">
                        Suscripcion: {selectedClaim.suscripcion?.plan?.nombre_plan || 'No asociada'}
                      </span>
                      <span className="workspace-subtle">
                        Pago: {selectedClaim.pago ? formatCurrency(selectedClaim.pago.monto, selectedClaim.pago.moneda) : 'No asociado'}
                      </span>
                      <span className="workspace-subtle">
                        Comercio: {selectedClaim.comercio?.nombre_comercio || 'Sin comercio asociado'}
                      </span>
                    </div>
                  </div>
                </article>
              ) : (
                <WorkspaceState
                  title="Selecciona un caso"
                  message="Elige un reclamo del historial para ver su detalle, respuesta y contexto asociado."
                />
              )}
            </div>
          </div>
        </section>
      )}

      {activeSection === 'subscriptions' && (
        <section>
          <div className="workspace-section__header">
            <div>
              <h2>Mis suscripciones</h2>
              <p>Gestiona renovaciones activas y conserva una vista clara del vencimiento.</p>
            </div>
          </div>

          {suscripciones.length === 0 ? (
            <WorkspaceState
              title="Aun no tienes suscripciones"
              message="Explora el catalogo de planes para activar tu primer servicio."
              actionLabel="Ir a explorar"
              onAction={() => setActiveSection('plans')}
            />
          ) : (
            <div className="workspace-grid workspace-grid--dual">
              {suscripciones.map((sub) => (
                <article key={sub.id_suscripcion} className="workspace-card">
                  <div className="workspace-card__header">
                    <div>
                      <h3>{sub.plan?.nombre_plan || 'Plan'}</h3>
                      <p>{sub.plan?.comercio?.nombre_comercio || 'Comercio sin nombre'}</p>
                    </div>
                    <WorkspacePill
                      label={sub.estado === 'activa' ? 'Activa' : 'Cancelada'}
                      tone={sub.estado === 'activa' ? 'success' : 'neutral'}
                    />
                  </div>

                  <div className="workspace-stack">
                    <span className="workspace-subtle">Frecuencia: {sub.plan?.frecuencia || 'N/A'}</span>
                    <span className="workspace-subtle">Vence: {formatDate(sub.fecha_fin)}</span>
                    <span className="workspace-subtle">
                      Precio: {sub.plan ? formatCurrency(sub.plan.precio, sub.plan.moneda) : 'Sin monto'}
                    </span>
                  </div>

                  {sub.estado === 'activa' && (
                    <div className="workspace-form__actions" style={{ marginTop: 18, justifyContent: 'flex-start' }}>
                      <button
                        type="button"
                        className="workspace-button workspace-button--ghost"
                        onClick={() => cancelarSuscripcion(sub.id_suscripcion)}
                      >
                        Cancelar suscripcion
                      </button>
                    </div>
                  )}
                </article>
              ))}
            </div>
          )}
        </section>
      )}

      {activeSection === 'payments' && (
        <section>
          <div className="workspace-section__header">
            <div>
              <h2>Historial de pagos</h2>
              <p>Consulta montos, estatus y fecha de cada transaccion.</p>
            </div>
          </div>

          {pagos.length === 0 ? (
            <WorkspaceState
              title="No hay pagos registrados"
              message="Cuando generes tu primer pago, aparecera aqui con su estado y referencia."
            />
          ) : (
            <div className="workspace-table">
              <table>
                <thead>
                  <tr>
                    <th>Plan</th>
                    <th>Comercio</th>
                    <th>Monto</th>
                    <th>Estado</th>
                    <th>Fecha</th>
                  </tr>
                </thead>
                <tbody>
                  {pagos.map((pago) => (
                    <tr key={pago.id_pago}>
                      <td>{pago.suscripcion?.plan?.nombre_plan || 'Plan'}</td>
                      <td>{pago.suscripcion?.plan?.comercio?.nombre_comercio || 'Comercio'}</td>
                      <td>{formatCurrency(pago.monto, pago.moneda)}</td>
                      <td>{pago.estatus_pago}</td>
                      <td>{formatDate(pago.created_at)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>
      )}

      {activeSection === 'plans' && (
        <section>
          <div className="workspace-section__header">
            <div>
              <h2>Explorar planes</h2>
              <p>Descubre la oferta activa y compara precios o frecuencias.</p>
            </div>
          </div>

          {planes.length === 0 ? (
            <WorkspaceState
              title="No hay planes disponibles"
              message="Aun no se han publicado planes activos en la plataforma."
            />
          ) : (
            <div className="workspace-grid workspace-grid--dual">
              {planes.slice(0, 10).map((plan) => (
                <article key={plan.id_plan} className="workspace-card">
                  <div className="workspace-card__header">
                    <div>
                      <h3>{plan.nombre_plan}</h3>
                      <p>{plan.comercio?.nombre_comercio || 'Comercio'}</p>
                    </div>
                    <WorkspacePill label={plan.estado ? 'Activo' : 'Inactivo'} tone={plan.estado ? 'success' : 'neutral'} />
                  </div>

                  <p className="workspace-subtle" style={{ lineHeight: 1.7 }}>
                    {plan.descripcion || 'Sin descripcion adicional.'}
                  </p>

                  <div className="workspace-kpis" style={{ marginTop: 18 }}>
                    <WorkspacePill label={formatCurrency(plan.precio, plan.moneda)} tone="neutral" />
                    <WorkspacePill label={plan.frecuencia} tone="neutral" />
                    <WorkspacePill label={plan.modalidad_cobro || 'prepago'} tone="neutral" />
                  </div>
                </article>
              ))}
            </div>
          )}
        </section>
      )}
    </WorkspaceLayout>
  );
};

export default ClienteDashboard;
