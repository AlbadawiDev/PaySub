import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import '../App.css';

const ComercioDashboard = () => {
  const navigate = useNavigate();
  const [activeSection, setActiveSection] = useState('dashboard');
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  
  // Nuevo estado para la lista de suscriptores
  const [suscriptores, setSuscriptores] = useState([]);

  useEffect(() => {
    const fetchUserData = async () => {
      const token = localStorage.getItem('access_token');
      if (!token) {
        navigate('/login');
        return;
      }

      try {
        const response = await fetch('http://localhost:8000/api/user', {
          headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json'
          }
        });

        if (response.ok) {
          const data = await response.json();
          setUser(data);
        } else {
          localStorage.clear();
          navigate('/login');
        }
      } catch (error) {
        console.error("Error cargando perfil de comercio:", error);
      } finally {
        setLoading(false);
      }
    };

    fetchUserData();
  }, [navigate]);

  // Nuevo Effect: Cargar suscriptores solo cuando entramos a la pestaña
  useEffect(() => {
    if (activeSection === 'suscriptores') {
      fetchSuscriptores();
    }
  }, [activeSection]);

  const fetchSuscriptores = async () => {
    const token = localStorage.getItem('access_token');
    try {
      // Ajusta esta ruta si tu equipo le puso otro nombre en api.php
      const response = await fetch('http://localhost:8000/api/suscripciones', {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Accept': 'application/json'
        }
      });
      if (response.ok) {
        const data = await response.json();
        setSuscriptores(data);
      }
    } catch (error) {
      console.error("Error cargando la lista:", error);
    }
  };

  const cambiarEstado = async (idSuscripcion, estadoActual) => {
    const token = localStorage.getItem('access_token');
    // Aplicando tu regla de negocio del UC-02
    const nuevoEstado = estadoActual === 'Activa' ? 'Cancelada' : 'Activa';

    try {
      const response = await fetch(`http://localhost:8000/api/suscripciones/${idSuscripcion}/estado`, {
        method: 'PUT', // o PATCH, según tu controlador
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({ estado: nuevoEstado })
      });

      if (response.ok) {
        // Actualizamos la tabla visualmente sin tener que recargar la página
        setSuscriptores(suscriptores.map(sub =>
          sub.id_suscripcion === idSuscripcion ? { ...sub, estado: nuevoEstado } : sub
        ));
      } else {
        alert("Error al intentar cambiar el estado.");
      }
    } catch (error) {
      console.error("Error cambiando estado:", error);
    }
  };

  const handleLogout = () => {
    localStorage.clear();
    navigate('/login');
  };

  if (loading) return (
    <div className="login-container" style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', color: 'white' }}>
      <h2>Cargando Panel de Comercio...</h2>
    </div>
  );

  return (
    <div className="login-container">
      <div className="particles">
        {Array.from({ length: 10 }, (_, i) => (
          <div key={i} className="particle"></div>
        ))}
      </div>

      <header className="navbar">
        <div className="logo"><span>PaySub 💼</span></div>
        <div className="user-info">
          <div className="user-avatar" style={{ 
            display: 'flex', alignItems: 'center', justifyContent: 'center', 
            background: 'linear-gradient(45deg, #00d4ff, #00ff87)', fontWeight: 'bold', color: 'black' 
          }}>
            {user?.nombre?.charAt(0) || 'C'}
          </div>
          <span className="user-name">{user?.nombre} (Comercio)</span>
        </div>
        <button onClick={handleLogout} className="btn-volver">Cerrar Sesión</button>
      </header>

      <div className="content-wrapper" style={{ paddingTop: '80px' }}>
        <div className="sidebar">
          <ul className="menu-options">
            <li className={`menu-item ${activeSection === 'dashboard' ? 'active' : ''}`} onClick={() => setActiveSection('dashboard')}>Dashboard</li>
            <li className={`menu-item ${activeSection === 'planes' ? 'active' : ''}`} onClick={() => setActiveSection('planes')}>Mis Planes</li>
            {/* Nueva pestaña en el menú */}
            <li className={`menu-item ${activeSection === 'suscriptores' ? 'active' : ''}`} onClick={() => setActiveSection('suscriptores')}>Suscriptores</li>
            <li className={`menu-item ${activeSection === 'ventas' ? 'active' : ''}`} onClick={() => setActiveSection('ventas')}>Ventas</li>
            <li className={`menu-item ${activeSection === 'config' ? 'active' : ''}`} onClick={() => setActiveSection('config')}>Configuración</li>
          </ul>
        </div>

        <div className="main-panel">
          {activeSection === 'dashboard' && (
            <section className="section-panel active">
              <h2 className="panel-title">Panel de Control: {user?.nombre}</h2>
              <p>Resumen de tus suscripciones vendidas.</p>
              <div style={{ marginTop: '20px', display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '20px' }}>
                <div style={{ background: 'rgba(0, 212, 255, 0.1)', padding: '20px', borderRadius: '15px', border: '1px solid #00d4ff' }}>
                  <h3 style={{ fontSize: '0.9rem' }}>Planes Activos</h3>
                  <p style={{ fontSize: '2.5rem', fontWeight: 'bold' }}>{user?.planes_count || 0}</p>
                </div>
                <div style={{ background: 'rgba(0, 255, 135, 0.1)', padding: '20px', borderRadius: '15px', border: '1px solid #00ff87' }}>
                  <h3 style={{ fontSize: '0.9rem' }}>Suscriptores Totales</h3>
                  <p style={{ fontSize: '2.5rem', fontWeight: 'bold' }}>{suscriptores.length || 0}</p>
                </div>
              </div>
            </section>
          )}

          {activeSection === 'planes' && (
            <section className="section-panel active">
              <h2 className="panel-title">Mis Planes</h2>
              <p>Aquí podrás crear y editar tus ofertas de suscripción.</p>
              <button style={{padding: '10px 20px', marginTop: '20px', borderRadius: '8px', cursor: 'pointer', background: '#00d4ff', color: 'black', fontWeight: 'bold'}}>+ Crear Nuevo Plan</button>
            </section>
          )}

          {/* Nueva Sección Renderizada */}
          {activeSection === 'suscriptores' && (
            <section className="section-panel active">
              <h2 className="panel-title">Gestión de Suscriptores</h2>
              <p>Administra el estado de los clientes asociados a tus planes.</p>
              
              <div style={{ marginTop: '20px', overflowX: 'auto' }}>
                <table style={{ width: '100%', borderCollapse: 'collapse', color: 'white' }}>
                  <thead>
                    <tr style={{ borderBottom: '2px solid #00d4ff', textAlign: 'left' }}>
                      <th style={{ padding: '10px' }}>Cliente</th>
                      <th style={{ padding: '10px' }}>Plan Contratado</th>
                      <th style={{ padding: '10px' }}>Vencimiento</th>
                      <th style={{ padding: '10px' }}>Estado</th>
                      <th style={{ padding: '10px' }}>Acción</th>
                    </tr>
                  </thead>
                  <tbody>
                    {suscriptores.length > 0 ? (
                      suscriptores.map((sub) => (
                        <tr key={sub.id_suscripcion} style={{ borderBottom: '1px solid rgba(255,255,255,0.1)' }}>
                          <td style={{ padding: '10px' }}>{sub.usuario?.nombre || 'Desconocido'}</td>
                          <td style={{ padding: '10px' }}>{sub.plan?.nombre_plan || 'Plan Base'}</td>
                          <td style={{ padding: '10px' }}>{new Date(sub.fecha_fin).toLocaleDateString()}</td>
                          <td style={{ padding: '10px', color: sub.estado === 'Activa' ? '#00ff87' : '#ff4d4d' }}>
                            {sub.estado}
                          </td>
                          <td style={{ padding: '10px' }}>
                            <button 
                              onClick={() => cambiarEstado(sub.id_suscripcion, sub.estado)}
                              style={{
                                padding: '5px 15px', 
                                borderRadius: '5px', 
                                background: sub.estado === 'Activa' ? 'rgba(255, 77, 77, 0.2)' : 'rgba(0, 255, 135, 0.2)',
                                color: sub.estado === 'Activa' ? '#ff4d4d' : '#00ff87',
                                border: `1px solid ${sub.estado === 'Activa' ? '#ff4d4d' : '#00ff87'}`,
                                cursor: 'pointer'
                              }}
                            >
                              {sub.estado === 'Activa' ? 'Cancelar' : 'Reactivar'}
                            </button>
                          </td>
                        </tr>
                      ))
                    ) : (
                      <tr>
                        <td colSpan="5" style={{ padding: '20px', textAlign: 'center' }}>No hay suscripciones registradas.</td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>
            </section>
          )}

        </div>
      </div>
    </div>
  );
};

export default ComercioDashboard;