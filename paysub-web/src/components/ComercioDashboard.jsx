import React, { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import '../App.css';
import { apiFetch, API_BASE_URL, getAuthHeaders } from '../config/api';

const cardStyle = {
  background: 'rgba(15, 23, 42, 0.65)',
  border: '1px solid rgba(148, 163, 184, 0.25)',
  borderRadius: '16px',
  padding: '18px',
  backdropFilter: 'blur(8px)',
};

const getPayload = (body) => body?.data ?? body;

const ComercioDashboard = () => {
  const navigate = useNavigate();
  const token = localStorage.getItem('access_token');

  const [activeSection, setActiveSection] = useState('dashboard');
  const [user, setUser] = useState(null);
  const [planes, setPlanes] = useState([]);
  const [suscripciones, setSuscripciones] = useState([]);
  const [pagos, setPagos] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  useEffect(() => {
    if (!token) {
      navigate('/login');
      return;
    }

    const loadData = async () => {
      setLoading(true);
      setError('');

      try {
        const [userRes, planesRes, susRes, pagosRes] = await Promise.all([
          apiFetch('/user', { token }),
          apiFetch('/mis-planes', { token }),
          apiFetch('/suscripciones', { token }),
          apiFetch('/pagos', { token }),
        ]);

        if (userRes.status === 401) {
          localStorage.clear();
          navigate('/login');
          return;
        }

        if (!userRes.ok) {
          throw new Error(userRes.message || 'No se pudo recuperar tu sesión.');
        }

        setUser(getPayload(userRes.payload));
        setPlanes(Array.isArray(getPayload(planesRes.payload)) ? getPayload(planesRes.payload) : []);
        setSuscripciones(Array.isArray(getPayload(susRes.payload)) ? getPayload(susRes.payload) : []);
        setPagos(Array.isArray(getPayload(pagosRes.payload)) ? getPayload(pagosRes.payload) : []);
      } catch (loadError) {
        setError(loadError.message || 'Error cargando panel de comercio.');
      } finally {
        setLoading(false);
      }
    };

    loadData();
  }, [navigate, token]);

  const ingresos = useMemo(() => pagos.reduce((sum, pago) => sum + Number(pago.monto || 0), 0), [pagos]);

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

  const cambiarEstado = async (idSuscripcion, estadoActual) => {
    setError('');
    setSuccess('');
    const nuevoEstado = estadoActual === 'activa' ? 'cancelada' : 'activa';

    try {
      const response = await fetch(`${API_BASE_URL}/suscripciones/${idSuscripcion}/estado`, {
        method: 'PUT',
        headers: getAuthHeaders(token, { 'Content-Type': 'application/json' }),
        body: JSON.stringify({ estado: nuevoEstado }),
      });

      const body = await response.json().catch(() => ({}));

      if (!response.ok) {
        throw new Error(body?.error || body?.message || body?.mensaje || 'No se pudo actualizar la suscripción.');
      }

      setSuscripciones((current) =>
        current.map((sub) => (sub.id_suscripcion === idSuscripcion ? { ...sub, estado: nuevoEstado } : sub))
      );
      setSuccess('Estado de suscripción actualizado exitosamente.');
    } catch (changeError) {
      setError(changeError.message);
    }
  };

  if (loading) {
    return <div className="login-container" style={{ color: 'white', padding: '30px' }}>Cargando panel de comercio...</div>;
  }

  if (!user) {
    return <div className="login-container" style={{ color: '#fecaca', padding: '30px' }}>No se pudo cargar tu perfil.</div>;
  }

  return (
    <div className="login-container">
      <header className="navbar" style={{ borderBottom: '1px solid rgba(148,163,184,0.25)' }}>
        <div className="logo"><span>PaySub Commerce</span></div>
        <div className="user-info">
          <div className="user-avatar" style={{ display: 'grid', placeItems: 'center', background: 'linear-gradient(135deg,#06b6d4,#34d399)', color: 'white' }}>
            {user?.nombre?.charAt(0) || 'C'}
          </div>
          <span className="user-name">{user?.nombre}</span>
        </div>
        <button onClick={handleLogout} className="btn-volver">Cerrar sesión</button>
      </header>

      <div className="content-wrapper" style={{ paddingTop: '88px' }}>
        <aside className="sidebar">
          <ul className="menu-options">
            {[
              ['dashboard', 'Resumen'],
              ['planes', 'Mis planes'],
              ['suscriptores', 'Suscriptores'],
              ['ventas', 'Ventas'],
            ].map(([key, label]) => (
              <li key={key} className={`menu-item ${activeSection === key ? 'active' : ''}`} onClick={() => setActiveSection(key)}>
                {label}
              </li>
            ))}
          </ul>
        </aside>

        <main className="main-panel">
          {error && <div style={{ ...cardStyle, borderColor: '#f87171', color: '#fecaca', marginBottom: 16 }}>{error}</div>}
          {success && <div style={{ ...cardStyle, borderColor: '#34d399', color: '#86efac', marginBottom: 16 }}>{success}</div>}

          {activeSection === 'dashboard' && (
            <section className="section-panel active">
              <h2 className="panel-title">Resumen del comercio</h2>
              <p style={{ color: '#cbd5e1' }}>Controla tus planes, suscriptores e ingresos desde un panel único.</p>
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(220px,1fr))', gap: 12, marginTop: 14 }}>
                <div style={cardStyle}><small>Planes registrados</small><h3>{planes.length}</h3></div>
                <div style={cardStyle}><small>Suscripciones activas</small><h3>{suscripciones.filter((s) => s.estado === 'activa').length}</h3></div>
                <div style={cardStyle}><small>Ingresos acumulados</small><h3>{ingresos.toFixed(2)} USD</h3></div>
              </div>
            </section>
          )}

          {activeSection === 'planes' && (
            <section className="section-panel active">
              <h2 className="panel-title">Mis planes</h2>
              {planes.length === 0 ? (
                <div style={cardStyle}>Aún no has creado planes.</div>
              ) : (
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(240px,1fr))', gap: 12 }}>
                  {planes.map((plan) => (
                    <article key={plan.id_plan} style={cardStyle}>
                      <h3 style={{ marginTop: 0 }}>{plan.nombre_plan}</h3>
                      <p style={{ color: '#cbd5e1' }}>{plan.descripcion || 'Sin descripción'}</p>
                      <strong>{plan.precio} {plan.moneda} / {plan.frecuencia}</strong>
                      <p style={{ marginBottom: 0 }}>Estado: {plan.estado ? 'activo' : 'inactivo'}</p>
                    </article>
                  ))}
                </div>
              )}
            </section>
          )}

          {activeSection === 'suscriptores' && (
            <section className="section-panel active">
              <h2 className="panel-title">Suscriptores</h2>
              {suscripciones.length === 0 ? (
                <div style={cardStyle}>No hay suscriptores en este momento.</div>
              ) : (
                <div style={{ ...cardStyle, overflowX: 'auto' }}>
                  <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                    <thead>
                      <tr>
                        <th style={{ textAlign: 'left', padding: 8 }}>Cliente</th>
                        <th style={{ textAlign: 'left', padding: 8 }}>Plan</th>
                        <th style={{ textAlign: 'left', padding: 8 }}>Estado</th>
                        <th style={{ textAlign: 'left', padding: 8 }}>Vence</th>
                        <th style={{ textAlign: 'left', padding: 8 }}>Acción</th>
                      </tr>
                    </thead>
                    <tbody>
                      {suscripciones.map((sub) => (
                        <tr key={sub.id_suscripcion}>
                          <td style={{ padding: 8 }}>{sub.usuario?.nombre || 'Cliente'}</td>
                          <td style={{ padding: 8 }}>{sub.plan?.nombre_plan || 'Plan'}</td>
                          <td style={{ padding: 8 }}>{sub.estado}</td>
                          <td style={{ padding: 8 }}>{sub.fecha_fin ? new Date(sub.fecha_fin).toLocaleDateString() : 'N/A'}</td>
                          <td style={{ padding: 8 }}>
                            <button className="btn-volver" onClick={() => cambiarEstado(sub.id_suscripcion, sub.estado)}>
                              {sub.estado === 'activa' ? 'Cancelar' : 'Reactivar'}
                            </button>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </section>
          )}

          {activeSection === 'ventas' && (
            <section className="section-panel active">
              <h2 className="panel-title">Historial de ventas</h2>
              {pagos.length === 0 ? (
                <div style={cardStyle}>No hay pagos registrados todavía.</div>
              ) : (
                <div style={{ ...cardStyle, overflowX: 'auto' }}>
                  <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                    <thead>
                      <tr>
                        <th style={{ textAlign: 'left', padding: 8 }}>Plan</th>
                        <th style={{ textAlign: 'left', padding: 8 }}>Cliente</th>
                        <th style={{ textAlign: 'left', padding: 8 }}>Monto</th>
                        <th style={{ textAlign: 'left', padding: 8 }}>Estatus</th>
                        <th style={{ textAlign: 'left', padding: 8 }}>Fecha</th>
                      </tr>
                    </thead>
                    <tbody>
                      {pagos.map((pago) => (
                        <tr key={pago.id_pago}>
                          <td style={{ padding: 8 }}>{pago.suscripcion?.plan?.nombre_plan || 'Plan'}</td>
                          <td style={{ padding: 8 }}>{pago.suscripcion?.usuario?.nombre || 'Cliente'}</td>
                          <td style={{ padding: 8 }}>{pago.monto} {pago.moneda}</td>
                          <td style={{ padding: 8 }}>{pago.estatus_pago}</td>
                          <td style={{ padding: 8 }}>{new Date(pago.created_at).toLocaleDateString()}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </section>
          )}
        </main>
      </div>
    </div>
  );
};

export default ComercioDashboard;
