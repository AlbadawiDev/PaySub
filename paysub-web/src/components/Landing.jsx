import React from 'react';
import '../App.css';
import Navbar from './Navbar';
import Hero from './Hero';

const Landing = () => {
  return (
    <div className="App">
      <Navbar />

      {/* HERO / LANDING PRINCIPAL */}
      <Hero />

      {/* SEGUNDA LANDING (NOSOTROS) */}
      <section className="landing-blanca" id="nosotros">
        <div className="contenedor-blanco">
          <h2>El problema que frena el <br />crecimiento de tu negocio</h2>
          <p style={{ margin: '0 auto', textAlign: 'center', whiteSpace: 'nowrap' }}>
            Pierdes tiempo, dinero y clientes por una gestión manual de pagos.
          </p>

          <div className="problem-cards">
            <div className="problem-card">
              <svg className="problem-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" width="56" height="56">
                <path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/>
              </svg>
              <h3>Gestión Manual y Desordenada</h3>
              <p>Pagos registrados a mano y sin control centralizado.</p>
            </div>

            <div className="problem-card">
              <svg className="problem-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" width="56" height="56">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 .88-.36 1.68-.93 2.25z"/>
              </svg>
              <h3>Experiencia Compleja para el Cliente</h3>
              <p>Clientes confundidos y pagos olvidados.</p>
            </div>

            <div className="problem-card">
              <svg className="problem-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" width="56" height="56">
                <path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"/>
              </svg>
              <h3>Ingresos Poco Predecibles</h3>
              <p>Ingresos inestables y difíciles de proyectar.</p>
            </div>
          </div>

          <a href="#servicios" className="btn-ver-mas">Cómo lo solucionamos</a>
        </div>
      </section>

      {/* LANDING PROPUESTA DE VALOR */}
      <section className="landing-valor" id="servicios">
        <div className="valor-container">
          <h2>Todo lo que puedes <br />hacer con PaySub</h2>
          <p className="valor-subtitle">
            Funcionalidades diseñadas para automatizar cobros, pagos y suscripciones.
          </p>

          <div className="valor-grid">
            <div className="valor-card">
              <h3>Para Comercios</h3>
              <ul>
                <li>Ingresos recurrentes y predecibles</li>
                <li>Automatización total de cobros</li>
                <li>Control centralizado de suscripciones</li>
                <li>Menos tareas manuales y errores</li>
              </ul>
            </div>

            <div className="valor-card">
              <h3>Para Clientes</h3>
              <ul>
                <li>Pagos simples y seguros</li>
                <li>Recordatorios automáticos</li>
                <li>Gestión clara de suscripciones</li>
                <li>Experiencia rápida y sin fricción</li>
              </ul>
            </div>
          </div>
        </div>
      </section>

      {/* FOOTER */}
      <footer className="footer-white">
        <div className="footer-content">
          <div className="footer-logo">
            {/* <img src="/logo.png" alt="PaySub logo" /> */}
            <span>PaySub</span>
          </div>

          <nav className="footer-nav">
            <a href="#">Ventana Principal</a>
            <a href="#nosotros">Problemática</a>
            <a href="#servicios">Qué ofrecemos</a>
          </nav>

          <div className="footer-socials">
            <a href="#" aria-label="Facebook"><i className="fab fa-facebook-f"></i></a>
            <a href="#" aria-label="Instagram"><i className="fab fa-instagram"></i></a>
            <a href="#" aria-label="Twitter"><i className="fab fa-twitter"></i></a>
            <a href="#" aria-label="LinkedIn"><i className="fab fa-linkedin-in"></i></a>
          </div>

          <div className="footer-divider"></div>

          <p className="footer-copy">
            © 2026 PaySub. Todos los derechos reservados.
          </p>
        </div>
      </footer>
    </div>
  );
};

export default Landing;