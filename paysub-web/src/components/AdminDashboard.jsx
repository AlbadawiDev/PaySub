import React, { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { apiFetch, API_BASE_URL, getAuthHeaders } from '../config/api';
import WorkspaceLayout, {
  WorkspaceMetricCard,
  WorkspacePill,
  WorkspaceState,
} from './shared/WorkspaceLayout';
import {
  CLAIM_STATUS_OPTIONS,
  formatCurrency,
  formatDate,
  getClaimContextLabel,
  getClaimPriorityLabel,
  getClaimStatusLabel,
  getClaimTone,
  getClaimTypeLabel,
  unwrapData,
} from '../utils/support';

const sections = [
  { key: 'overview', label: 'Metricas', caption: 'Panorama global del negocio' },
  { key: 'claims', label: 'Reclamos', caption: 'Bandeja administrativa central' },
  { key: 'activity', label: 'Actividad', caption: 'Pagos y top comercios' },
];

const emptyClaimForm = {
  estado: 'en_revision',
  respuesta_admin: '',
};

const AdminDashboard = () => {
  const navigate = useNavigate();
  const token = localStorage.getItem('access_token');

  const [activeSection, setActiveSection] = useState('overview');
  const [user, setUser] = useState(null);
  const [metrics, setMetrics] = useState(null);
  const [claims, setClaims] = useState([]);
  const [selectedClaimId, setSelectedClaimId] = useState(null);
  const [selectedClaim, setSelectedClaim] = useState(null);
  const [claimUpdate, setClaimUpdate] = useState(emptyClaimForm);
  const [claimFilter, setClaimFilter] = useState('todos');
  const [claimSearch, setClaimSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const [claimDetailLoading, setClaimDetailLoading] = useState(false);
  const [updatingClaim, setUpdatingClaim] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  const loadAdminData = async ({ preserveSelection = true } = {}) => {
    setLoading(true);
    setError('');

    try {
      const [userRes, metricsRes, claimsRes] = await Promise.all([
        apiFetch('/user', { token }),
        apiFetch('/admin/metricas', { token }),
        apiFetch('/reclamos', { token }),
      ]);

      if (userRes.status === 401) {
        localStorage.clear();
        navigate('/login');
        return;
      }

      if (!userRes.ok) {
        throw new Error(userRes.message || 'No se pudo recuperar la sesion administrativa.');
      }

      if (!metricsRes.ok) {
        throw new Error(metricsRes.message || 'No se pudieron recuperar las metricas globales.');
      }

      if (!claimsRes.ok) {
        throw new Error(claimsRes.message || 'No se pudieron recuperar los reclamos.');
      }

      const userPayload = unwrapData(userRes.payload);
      const claimsPayload = Array.isArray(unwrapData(claimsRes.payload)) ? unwrapData(claimsRes.payload) : [];

      setUser(userPayload);
      setMetrics(unwrapData(metricsRes.payload));
      setClaims(claimsPayload);

      if (!preserveSelection || !selectedClaimId) {
        setSelectedClaimId(claimsPayload[0]?.id_reclamo || null);
      } else if (!claimsPayload.some((item) => item.id_reclamo === selectedClaimId)) {
        setSelectedClaimId(claimsPayload[0]?.id_reclamo || null);
      }
    } catch (loadError) {
      setError(loadError.message || 'Error cargando panel administrador.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (!token) {
      navigate('/login');
      return;
    }

    loadAdminData({ preserveSelection: false });
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
          throw new Error(response.message || 'No fue posible abrir el detalle del reclamo.');
        }

        const payload = unwrapData(response.payload);
        setSelectedClaim(payload);
        setClaimUpdate({
          estado: payload.estado || 'en_revision',
          respuesta_admin: payload.respuesta_admin || '',
        });
      } catch (detailError) {
        setSelectedClaim(null);
        setError(detailError.message);
      } finally {
        setClaimDetailLoading(false);
      }
    };

    loadClaimDetail();
  }, [selectedClaimId, token]);

  const filteredClaims = useMemo(() => {
    return claims.filter((claim) => {
      const matchesStatus = claimFilter === 'todos' ? true : claim.estado === claimFilter;
      const search = claimSearch.trim().toLowerCase();
      const matchesSearch = search
        ? [
            claim.codigo,
            claim.asunto,
            claim.descripcion,
            claim.usuario?.nombre,
            claim.usuario?.apellido,
          ].some((value) => String(value || '').toLowerCase().includes(search))
        : true;

      return matchesStatus && matchesSearch;
    });
  }, [claimFilter, claimSearch, claims]);

  const chartClaims = metrics?.reclamos_por_estado || [];
  const chartPayments = metrics?.pagos_por_estado || [];
  const chartMaxClaims = Math.max(...chartClaims.map((item) => Number(item.total || 0)), 1);
  const chartMaxPayments = Math.max(...chartPayments.map((item) => Number(item.total || 0)), 1);

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

  const updateClaimStatus = async (event) => {
    event.preventDefault();

    if (!selectedClaimId) {
      return;
    }

    setUpdatingClaim(true);
    setError('');
    setSuccess('');

    try {
      const response = await apiFetch(`/reclamos/${selectedClaimId}/estado`, {
        token,
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: claimUpdate,
      });

      if (!response.ok) {
        throw new Error(response.message || 'No fue posible actualizar el reclamo.');
      }

      const updatedClaim = unwrapData(response.payload);

      setClaims((current) =>
        current.map((item) => (item.id_reclamo === updatedClaim.id_reclamo ? updatedClaim : item))
      );
      setSelectedClaim(updatedClaim);
      setSuccess('El reclamo fue actualizado y las metricas se refrescaran ahora.');
      await loadAdminData();
    } catch (updateError) {
      setError(updateError.message);
    } finally {
      setUpdatingClaim(false);
    }
  };

  if (loading) {
    return (
      <div className="workspace-shell">
        <div className="workspace-layout">
          <div className="workspace-main">
            <WorkspaceState
              title="Cargando panel administrador"
              message="Estamos agregando metricas globales, reclamos y actividad reciente."
            />
          </div>
        </div>
      </div>
    );
  }

  if (!user || !metrics) {
    return (
      <div className="workspace-shell">
        <div className="workspace-layout">
          <div className="workspace-main">
            <WorkspaceState
              title="Panel no disponible"
              message="No fue posible inicializar las metricas administrativas."
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
      productLabel="Panel administrador"
      user={user}
      sections={sections}
      activeSection={activeSection}
      onSectionChange={setActiveSection}
      onLogout={handleLogout}
      title="Operacion global y soporte"
      subtitle="Supervisa indicadores reales, analiza actividad y resuelve reclamos desde una sola bandeja."
      banner={
        error
          ? { tone: 'error', title: 'Atencion requerida', message: error }
          : success
            ? { tone: 'success', title: 'Actualizacion completada', message: success }
            : null
      }
    >
      {activeSection === 'overview' && (
        <section>
          <div className="workspace-section__header">
            <div>
              <h2>Metricas globales</h2>
              <p>Lectura ejecutiva del ecosistema PaySub con datos reales del backend.</p>
            </div>
          </div>

          <div className="workspace-grid workspace-grid--metrics">
            <WorkspaceMetricCard label="Usuarios" value={metrics.overview.usuarios_total} hint="Base total registrada" />
            <WorkspaceMetricCard label="Clientes" value={metrics.overview.clientes_total} hint="Segmento comprador" />
            <WorkspaceMetricCard label="Comercios" value={metrics.overview.comercios_total} hint="Negocios activos" />
            <WorkspaceMetricCard
              label="Suscripciones activas"
              value={metrics.overview.suscripciones_activas}
              hint="Servicios vigentes"
            />
            <WorkspaceMetricCard
              label="Ingresos totales"
              value={formatCurrency(metrics.overview.ingresos_totales, 'USD')}
              hint="Pagos completados"
              tone="success"
            />
            <WorkspaceMetricCard
              label="Ingresos del mes"
              value={formatCurrency(metrics.overview.ingresos_mes_actual, 'USD')}
              hint="Mes en curso"
              tone="success"
            />
            <WorkspaceMetricCard
              label="Reclamos abiertos"
              value={metrics.overview.reclamos_abiertos}
              hint="Abiertos o en revision"
              tone={metrics.overview.reclamos_abiertos > 0 ? 'warning' : 'default'}
            />
            <WorkspaceMetricCard
              label="Resolucion"
              value={`${metrics.overview.tasa_resolucion_reclamos}%`}
              hint={`${metrics.overview.reclamos_resueltos} casos resueltos`}
            />
          </div>

          <div className="workspace-grid workspace-grid--split" style={{ marginTop: 18 }}>
            <article className="workspace-chart">
              <div className="workspace-card__header">
                <div>
                  <h3>Reclamos por estado</h3>
                  <p>Distribucion operativa del centro de soporte.</p>
                </div>
              </div>
              <div className="workspace-chart__bars">
                {chartClaims.length === 0 ? (
                  <WorkspaceState
                    title="Sin datos de reclamos"
                    message="Aun no existen casos registrados en la plataforma."
                  />
                ) : (
                  chartClaims.map((item) => (
                    <div key={item.estado} className="workspace-chart__row">
                      <span>{getClaimStatusLabel(item.estado)}</span>
                      <div className="workspace-chart__track">
                        <div
                          className="workspace-chart__fill"
                          style={{ width: `${(Number(item.total || 0) / chartMaxClaims) * 100}%` }}
                        ></div>
                      </div>
                      <strong>{item.total}</strong>
                    </div>
                  ))
                )}
              </div>
            </article>

            <article className="workspace-chart">
              <div className="workspace-card__header">
                <div>
                  <h3>Pagos por estado</h3>
                  <p>Senales tempranas de friccion en cobro y conciliacion.</p>
                </div>
              </div>
              <div className="workspace-chart__bars">
                {chartPayments.length === 0 ? (
                  <WorkspaceState
                    title="Sin pagos registrados"
                    message="Los estados de pago apareceran aqui cuando exista actividad."
                  />
                ) : (
                  chartPayments.map((item) => (
                    <div key={item.estado} className="workspace-chart__row">
                      <span>{item.estado}</span>
                      <div className="workspace-chart__track">
                        <div
                          className="workspace-chart__fill"
                          style={{ width: `${(Number(item.total || 0) / chartMaxPayments) * 100}%` }}
                        ></div>
                      </div>
                      <strong>{item.total}</strong>
                    </div>
                  ))
                )}
              </div>
            </article>
          </div>

          <div className="workspace-grid workspace-grid--split" style={{ marginTop: 18 }}>
            <article className="workspace-card">
              <div className="workspace-card__header">
                <div>
                  <h3>Ultimos reclamos</h3>
                  <p>Los casos mas recientes que entraron al sistema.</p>
                </div>
              </div>

              <div className="workspace-list">
                {(metrics.ultimos_reclamos || []).map((claim) => (
                  <button
                    key={claim.id_reclamo}
                    type="button"
                    className="workspace-list__item"
                    onClick={() => {
                      setActiveSection('claims');
                      setSelectedClaimId(claim.id_reclamo);
                    }}
                  >
                    <strong>{claim.asunto}</strong>
                    <small>
                      {claim.codigo} · {claim.usuario?.nombre || 'Cliente'} · {formatDate(claim.created_at)}
                    </small>
                    <div className="workspace-kpis" style={{ marginTop: 12 }}>
                      <WorkspacePill label={getClaimStatusLabel(claim.estado)} tone={getClaimTone(claim.estado)} />
                      <WorkspacePill label={claim.comercio?.nombre_comercio || 'Caso general'} tone="neutral" />
                    </div>
                  </button>
                ))}
              </div>
            </article>

            <article className="workspace-card">
              <div className="workspace-card__header">
                <div>
                  <h3>Actividad por dia</h3>
                  <p>Ultimos siete dias de altas, casos y pagos.</p>
                </div>
              </div>

              <div className="workspace-table">
                <table>
                  <thead>
                    <tr>
                      <th>Fecha</th>
                      <th>Usuarios</th>
                      <th>Reclamos</th>
                      <th>Pagos</th>
                    </tr>
                  </thead>
                  <tbody>
                    {(metrics.registros_ultimos_7_dias || []).map((row) => (
                      <tr key={row.fecha}>
                        <td>{formatDate(row.fecha)}</td>
                        <td>{row.usuarios}</td>
                        <td>{row.reclamos}</td>
                        <td>{row.pagos}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </article>
          </div>
        </section>
      )}

      {activeSection === 'claims' && (
        <section>
          <div className="workspace-section__header">
            <div>
              <h2>Bandeja administrativa de reclamos</h2>
              <p>Consulta todos los casos, abre el detalle y cambia estado o respuesta al cliente.</p>
            </div>
          </div>

          <div className="workspace-split">
            <article className="workspace-card">
              <div className="workspace-card__header">
                <div>
                  <h3>Todos los reclamos</h3>
                  <p>Filtra por estado o busca por codigo, asunto o cliente.</p>
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
                    placeholder="Codigo, cliente o asunto"
                  />
                </label>
              </div>

              {filteredClaims.length === 0 ? (
                <WorkspaceState
                  title="Sin reclamos para este filtro"
                  message="Prueba otra combinacion de estado o texto de busqueda."
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
                      <small>
                        {claim.codigo} · {claim.usuario?.nombre || 'Cliente'} {claim.usuario?.apellido || ''}
                      </small>
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

            <div className="workspace-stack">
              {claimDetailLoading ? (
                <WorkspaceState
                  title="Cargando detalle del reclamo"
                  message="Estamos recuperando contexto, cliente y estado actual."
                />
              ) : selectedClaim ? (
                <>
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
                          <h3>Cliente</h3>
                          <p>Identidad del usuario que genero el caso.</p>
                        </div>
                      </div>
                      <div className="workspace-stack">
                        <span className="workspace-subtle">
                          {selectedClaim.usuario?.nombre || 'Cliente'} {selectedClaim.usuario?.apellido || ''}
                        </span>
                        <span className="workspace-subtle">{selectedClaim.usuario?.correo_electronico || 'Sin correo'}</span>
                        <span className="workspace-subtle">{selectedClaim.comercio?.nombre_comercio || 'Sin comercio asociado'}</span>
                      </div>
                    </div>
                  </article>

                  <form className="workspace-form" onSubmit={updateClaimStatus}>
                    <div className="workspace-card__header">
                      <div>
                        <h3>Gestion administrativa</h3>
                        <p>Actualiza el estado del caso y registra una respuesta visible para el cliente.</p>
                      </div>
                    </div>

                    <label>
                      Estado
                      <select
                        name="estado"
                        value={claimUpdate.estado}
                        onChange={(event) =>
                          setClaimUpdate((current) => ({ ...current, estado: event.target.value }))
                        }
                      >
                        {CLAIM_STATUS_OPTIONS.map((option) => (
                          <option key={option.value} value={option.value}>{option.label}</option>
                        ))}
                      </select>
                    </label>

                    <label style={{ marginTop: 14 }}>
                      Respuesta administrativa
                      <textarea
                        name="respuesta_admin"
                        value={claimUpdate.respuesta_admin}
                        onChange={(event) =>
                          setClaimUpdate((current) => ({ ...current, respuesta_admin: event.target.value }))
                        }
                        placeholder="Describe la accion tomada, proximo paso o resolucion del caso."
                      />
                    </label>

                    <div className="workspace-form__actions">
                      <button
                        type="button"
                        className="workspace-button workspace-button--ghost"
                        onClick={() =>
                          setClaimUpdate({
                            estado: selectedClaim.estado,
                            respuesta_admin: selectedClaim.respuesta_admin || '',
                          })
                        }
                      >
                        Revertir cambios
                      </button>
                      <button type="submit" className="workspace-button workspace-button--primary" disabled={updatingClaim}>
                        {updatingClaim ? 'Actualizando...' : 'Guardar estado'}
                      </button>
                    </div>
                  </form>
                </>
              ) : (
                <WorkspaceState
                  title="Selecciona un reclamo"
                  message="El detalle administrativo aparecera aqui cuando elijas un caso de la lista."
                />
              )}
            </div>
          </div>
        </section>
      )}

      {activeSection === 'activity' && (
        <section>
          <div className="workspace-section__header">
            <div>
              <h2>Actividad comercial</h2>
              <p>Pagos recientes y comercios con mayor traccion para lectura operativa rapida.</p>
            </div>
          </div>

          <div className="workspace-grid workspace-grid--split">
            <article className="workspace-card">
              <div className="workspace-card__header">
                <div>
                  <h3>Ultimos pagos</h3>
                  <p>Eventos recientes en el pipeline de cobro.</p>
                </div>
              </div>
              {(metrics.ultimos_pagos || []).length === 0 ? (
                <WorkspaceState
                  title="Sin actividad de pagos"
                  message="Todavia no hay movimientos registrados en el historial."
                />
              ) : (
                <div className="workspace-table">
                  <table>
                    <thead>
                      <tr>
                        <th>Comercio</th>
                        <th>Plan</th>
                        <th>Cliente</th>
                        <th>Monto</th>
                        <th>Estado</th>
                      </tr>
                    </thead>
                    <tbody>
                      {(metrics.ultimos_pagos || []).map((pago) => (
                        <tr key={pago.id_pago}>
                          <td>{pago.suscripcion?.plan?.comercio?.nombre_comercio || 'Comercio'}</td>
                          <td>{pago.suscripcion?.plan?.nombre_plan || 'Plan'}</td>
                          <td>{pago.suscripcion?.usuario?.nombre || 'Cliente'}</td>
                          <td>{formatCurrency(pago.monto, pago.moneda)}</td>
                          <td>{pago.estatus_pago}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </article>

            <article className="workspace-card">
              <div className="workspace-card__header">
                <div>
                  <h3>Top comercios</h3>
                  <p>Ordenados por ingresos completados acumulados.</p>
                </div>
              </div>
              {(metrics.top_comercios || []).length === 0 ? (
                <WorkspaceState
                  title="Sin comercios con facturacion"
                  message="Los rankings se poblaran conforme entren pagos completados."
                />
              ) : (
                <div className="workspace-list">
                  {(metrics.top_comercios || []).map((commerce) => (
                    <div key={commerce.id_comercio} className="workspace-list__item">
                      <strong>{commerce.nombre_comercio}</strong>
                      <small>{commerce.suscripciones_total} suscripciones historicas</small>
                      <div className="workspace-kpis" style={{ marginTop: 12 }}>
                        <WorkspacePill
                          label={formatCurrency(commerce.ingresos_totales, 'USD')}
                          tone="success"
                        />
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </article>
          </div>
        </section>
      )}
    </WorkspaceLayout>
  );
};

export default AdminDashboard;
