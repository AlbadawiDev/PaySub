import React, { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import '../App.css';
import { apiFetch, API_BASE_URL, getAuthHeaders } from '../config/api';

const sectionCardStyle = {
  background: 'rgba(15, 23, 42, 0.65)',
  border: '1px solid rgba(148, 163, 184, 0.25)',
  borderRadius: '16px',
  padding: '20px',
  backdropFilter: 'blur(8px)',
};

const metricStyle = {
  ...sectionCardStyle,
  minHeight: '120px',
  display: 'flex',
  flexDirection: 'column',
  justifyContent: 'space-between',
};

const getPayload = (responseBody) => responseBody?.data ?? responseBody;

const ClienteDashboard = () => {
  const navigate = useNavigate();
  const [activeSection, setActiveSection] = useState('dashboard');
  const [user, setUser] = useState(null);
  const [suscripciones, setSuscripciones] = useState([]);
  const [pagos, setPagos] = useState([]);
  const [planes, setPlanes] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  const token = localStorage.getItem('access_token');

  useEffect(() => {
    if (!token) {
      navigate('/login');
      return;
    }

    const loadDashboard = async () => {
      setLoading(true);
      setError('');

      try {
        const [userRes, susRes, pagosRes, planesRes] = await Promise.all([
          apiFetch('/user', { token }),
          apiFetch('/mis-suscripciones', { token }),
          apiFetch('/pagos', { token }),
          apiFetch('/planes', { token }),
        ]);

        if (userRes.status === 401) {
          localStorage.clear();
          navigate('/login');
          return;
        }

        if (!userRes.ok) {
          throw new Error(userRes.message || 'No pudimos recuperar tu sesión. Inicia nuevamente.');
        }

        setUser(getPayload(userRes.payload));
        setSuscripciones(Array.isArray(getPayload(susRes.payload)) ? getPayload(susRes.payload) : []);
        setPagos(Array.isArray(getPayload(pagosRes.payload)) ? getPayload(pagosRes.payload) : []);
        setPlanes(Array.isArray(getPayload(planesRes.payload)) ? getPayload(planesRes.payload) : []);
      } catch (fetchError) {
        setError(fetchError.message || 'Ocurrió un error cargando tu panel.');
      } finally {
        setLoading(false);
      }
    };

    loadDashboard();
  }, [navigate, token]);

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
        throw new Error(payload?.error || payload?.message || payload?.mensaje || 'No se pudo cancelar la suscripción.');
      }

      setSuscripciones((current) =>
        current.map((item) =>
          item.id_suscripcion === idSuscripcion ? { ...item, estado: 'cancelada' } : item
        )
      );
      setSuccess('Suscripción cancelada exitosamente.');
    } catch (cancelError) {
      setError(cancelError.message);
    }
  };

  const proximosPagos = useMemo(
    () =>
      suscripciones
        .filter((s) => s.estado === 'activa')
        .sort((a, b) => new Date(a.fecha_fin) - new Date(b.fecha_fin))
        .slice(0, 3),
    [suscripciones]
  );

  if (loading) {
    return <div className="login-container" style={{ color: 'white', padding: '30px' }}>Cargando panel de cliente...</div>;
  }

  if (!user) {
    return <div className="login-container" style={{ color: '#fecaca', padding: '30px' }}>No se pudo cargar tu perfil.</div>;
  }

  return (
    <div className="login-container">
      <header className="navbar" style={{ borderBottom: '1px solid rgba(148,163,184,0.25)' }}>
        <div className="logo"><span>PaySub</span></div>
        <div className="user-info">
          <div className="user-avatar" style={{ display: 'grid', placeItems: 'center', background: 'linear-gradient(135deg,#6366f1,#06b6d4)', color: 'white' }}>
            {user?.nombre?.charAt(0) || 'C'}
          </div>
          <span className="user-name">{user?.nombre} {user?.apellido}</span>
        </div>
        <button onClick={handleLogout} className="btn-volver">Cerrar sesión</button>
      </header>

      <div className="content-wrapper" style={{ paddingTop: '88px' }}>
        <aside className="sidebar">
          <ul className="menu-options">
            {[
              ['dashboard', 'Resumen'],
              ['suscripciones', 'Mis suscripciones'],
              ['pagos', 'Historial de pagos'],
              ['explorar', 'Explorar planes'],
            ].map(([key, label]) => (
              <li key={key} className={`menu-item ${activeSection === key ? 'active' : ''}`} onClick={() => setActiveSection(key)}>
                {label}
              </li>
            ))}
          </ul>
        </aside>

        <main className="main-panel">
          {error && <div style={{ ...sectionCardStyle, borderColor: '#f87171', color: '#fecaca', marginBottom: 16 }}>{error}</div>}
          {success && <div style={{ ...sectionCardStyle, borderColor: '#34d399', color: '#86efac', marginBottom: 16 }}>{success}</div>}

          {activeSection === 'dashboard' && (
            <section className="section-panel active">
              <h2 className="panel-title">Bienvenido de nuevo, {user.nombre}</h2>
              <p style={{ color: '#cbd5e1' }}>Gestiona tus suscripciones y mantén control de tus próximos cobros.</p>

              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(220px,1fr))', gap: 16, marginTop: 16 }}>
                <div style={metricStyle}><small>Suscripciones activas</small><strong style={{ fontSize: 32 }}>{suscripciones.filter((s) => s.estado === 'activa').length}</strong></div>
                <div style={metricStyle}><small>Pagos registrados</small><strong style={{ fontSize: 32 }}>{pagos.length}</strong></div>
                <div style={metricStyle}><small>Próximo vencimiento</small><strong>{proximosPagos[0]?.fecha_fin ? new Date(proximosPagos[0].fecha_fin).toLocaleDateString() : 'Sin datos'}</strong></div>
              </div>
            </section>
          )}

          {activeSection === 'suscripciones' && (
            <section className="section-panel active">
              <h2 className="panel-title">Mis suscripciones</h2>
              {suscripciones.length === 0 ? (
                <div style={sectionCardStyle}>Aún no tienes suscripciones activas.</div>
              ) : (
                <div style={{ display: 'grid', gap: 12 }}>
                  {suscripciones.map((sub) => (
                    <article key={sub.id_suscripcion} style={sectionCardStyle}>
                      <h3 style={{ marginTop: 0 }}>{sub.plan?.nombre_plan || 'Plan'}</h3>
                      <p style={{ color: '#cbd5e1' }}>Estado: <strong>{sub.estado}</strong></p>
                      <p style={{ color: '#cbd5e1' }}>Vence: {sub.fecha_fin ? new Date(sub.fecha_fin).toLocaleDateString() : 'N/A'}</p>
                      {sub.estado === 'activa' && (
                        <button className="btn-volver" onClick={() => cancelarSuscripcion(sub.id_suscripcion)}>
                          Cancelar suscripción
                        </button>
                      )}
                    </article>
                  ))}
                </div>
              )}
            </section>
          )}

          {activeSection === 'pagos' && (
            <section className="section-panel active">
              <h2 className="panel-title">Historial de pagos</h2>
              {pagos.length === 0 ? (
                <div style={sectionCardStyle}>No hay pagos registrados.</div>
              ) : (
                <div style={{ ...sectionCardStyle, overflowX: 'auto' }}>
                  <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                    <thead>
                      <tr>
                        <th style={{ textAlign: 'left', padding: 8 }}>Plan</th>
                        <th style={{ textAlign: 'left', padding: 8 }}>Monto</th>
                        <th style={{ textAlign: 'left', padding: 8 }}>Estado</th>
                        <th style={{ textAlign: 'left', padding: 8 }}>Fecha</th>
                      </tr>
                    </thead>
                    <tbody>
                      {pagos.map((pago) => (
                        <tr key={pago.id_pago}>
                          <td style={{ padding: 8 }}>{pago.suscripcion?.plan?.nombre_plan || 'Plan'}</td>
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

          {activeSection === 'explorar' && (
            <section className="section-panel active">
              <h2 className="panel-title">Explorar planes</h2>
              {planes.length === 0 ? (
                <div style={sectionCardStyle}>No hay planes disponibles en este momento.</div>
              ) : (
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(220px,1fr))', gap: 12 }}>
                  {planes.slice(0, 8).map((plan) => (
                    <article key={plan.id_plan} style={sectionCardStyle}>
                      <h3 style={{ marginTop: 0 }}>{plan.nombre_plan}</h3>
                      <p style={{ color: '#cbd5e1' }}>{plan.comercio?.nombre_comercio || 'Comercio'}</p>
                      <strong>{plan.precio} {plan.moneda} / {plan.frecuencia}</strong>
                    </article>
                  ))}
                </div>
              )}
            </section>
          )}
        </main>
      </div>
    </div>
  );
};

export default ClienteDashboard;
