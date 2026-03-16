import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import '../App.css';

const ClienteDashboard = () => {
  const navigate = useNavigate();
  const [activeSection, setActiveSection] = useState('dashboard');
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  // EFECTO PARA CARGAR DATOS REALES DEL BACKEND
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
        console.error("Error cargando perfil:", error);
      } finally {
        setLoading(false);
      }
    };

    fetchUserData();
  }, [navigate]);

  // FUNCIÓN PARA CERRAR SESIÓN
  const handleLogout = () => {
    localStorage.clear();
    navigate('/login');
  };

  const handleMenuClick = (section) => {
    setActiveSection(section);
  };

  if (loading) {
    return (
      <div className="login-container" style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', color: 'white' }}>
        <h2>Cargando tu panel...</h2>
      </div>
    );
  }

  return (
    <div className="login-container">
      {/* PARTÍCULAS DE FONDO (Se mantiene estética original) */}
      <div className="particles">
        {Array.from({ length: 10 }, (_, i) => (
          <div key={i} className="particle"></div>
        ))}
      </div>

      {/* NAVBAR ACTUALIZADA */}
      <header className="navbar">
        <div className="logo">
          <span>PaySub</span>
        </div>

        <div className="user-info">
          {/* Avatar dinámico con la inicial del nombre real */}
          <div className="user-avatar" style={{ 
            display: 'flex', alignItems: 'center', justifyContent: 'center', 
            background: 'linear-gradient(45deg, #7000ff, #00d4ff)', fontWeight: 'bold', color: 'white' 
          }}>
            {user?.nombre?.charAt(0) || 'U'}
          </div>
          <span className="user-name">{user?.nombre} {user?.apellido}</span>
        </div>

        {/* Botón de cerrar sesión en lugar de volver */}
        <button onClick={handleLogout} className="btn-volver" style={{ background: 'transparent', border: '1px solid rgba(255,255,255,0.2)', cursor: 'pointer' }}>
          Cerrar Sesión
        </button>
      </header>

      <div className="content-wrapper" style={{ paddingTop: '80px' }}>
        
        {/* PANEL LATERAL IZQUIERDO (Se mantiene original) */}
        <div className="sidebar">
          <ul className="menu-options">
            <li className={`menu-item ${activeSection === 'dashboard' ? 'active' : ''}`} onClick={() => handleMenuClick('dashboard')}>
              Dashboard
            </li>
            <li className={`menu-item ${activeSection === 'explorar' ? 'active' : ''}`} onClick={() => handleMenuClick('explorar')}>
              Explorar Planes
            </li>
            <li className={`menu-item ${activeSection === 'suscripciones' ? 'active' : ''}`} onClick={() => handleMenuClick('suscripciones')}>
              Mis Suscripciones
            </li>
            <li className={`menu-item ${activeSection === 'historial' ? 'active' : ''}`} onClick={() => handleMenuClick('historial')}>
              Historial de Pagos
            </li>
          </ul>
        </div>

        {/* PANEL PRINCIPAL CON DATOS DINÁMICOS */}
        <div className="main-panel">
          
          <section id="dashboard" className={`section-panel ${activeSection === 'dashboard' ? 'active' : ''}`}>
            <h2 className="panel-title">Bienvenido, {user?.nombre} 👋</h2>
            <p>Este es el resumen de tu cuenta de cliente.</p>
            <div style={{ marginTop: '20px', display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '20px' }}>
                <div style={{ background: 'rgba(255,255,255,0.05)', padding: '20px', borderRadius: '15px', border: '1px solid rgba(255,255,255,0.1)' }}>
                  <h3 style={{ fontSize: '0.9rem', color: '#aaa' }}>Suscripciones Activas</h3>
                  <p style={{ fontSize: '2.5rem', fontWeight: 'bold', margin: '10px 0' }}>{user?.suscripciones_count || 0}</p>
                </div>
            </div>
          </section>

          <section id="explorar" className={`section-panel ${activeSection === 'explorar' ? 'active' : ''}`}>
            <h2 className="panel-title">Explorar Planes</h2>
            <p>Consulta los servicios disponibles para suscribirte.</p>
          </section>

          <section id="suscripciones" className={`section-panel ${activeSection === 'suscripciones' ? 'active' : ''}`}>
            <h2 className="panel-title">Mis Suscripciones</h2>
            <p>Aquí aparecerán tus servicios contratados actualmente.</p>
          </section>

          <section id="historial" className={`section-panel ${activeSection === 'historial' ? 'active' : ''}`}>
            <h2 className="panel-title">Historial de Pagos</h2>
            <p>Consulta tus facturas y recibos anteriores.</p>
          </section>

        </div>
      </div>
    </div>
  );
};

export default ClienteDashboard;