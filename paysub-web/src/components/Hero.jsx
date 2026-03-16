import React, { useEffect } from 'react';
import { Link } from 'react-router-dom';
import seguridad from '../assets/seguridad.png';
import rapidez from '../assets/rapidez.png';
import controlTotal from '../assets/control total.png';

const Hero = () => {
  // Lógica de animación portada a React
  useEffect(() => {
    const handleScroll = () => {
      const card = document.getElementById('card-parallax');
      const seccionProblema = document.getElementById('nosotros');
      const seccionServicios = document.getElementById('servicios');
      
      // Verificamos que los elementos existan antes de calcular
      if (!card || !seccionProblema || !seccionServicios) return;

      const posProblema = seccionProblema.getBoundingClientRect();
      const posServicios = seccionServicios.getBoundingClientRect();

      // Lógica de "Modo Futurista" (Desvanecimiento)
      if (posProblema.top < window.innerHeight / 2 && posServicios.top > window.innerHeight / 2) {
        card.classList.add('modo-futurista');
        card.classList.remove('fade-final');
      } else if (posServicios.top < window.innerHeight / 2) {
        card.classList.add('fade-final');
        card.classList.remove('modo-futurista');
      } else {
        // Estado normal (Hero)
        card.classList.remove('modo-futurista');
        card.classList.remove('fade-final');
        card.style.transform = `translateY(${window.scrollY * 0.4}px)`;
      }
    };

    window.addEventListener('scroll', handleScroll);
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  return (
    <section className="hero">
      {/* Texto Principal */}
      <div className="hero-text">
        <h1>Suscripciones y<br/>Pagos Inteligentes</h1>
        <p className="subtitle">
          La plataforma que conecta a clientes y comercios a través<br/>
          de transacciones rápidas y seguras.
        </p>
        <div className="buttons">
          <Link to="/registro" className="btn-secondary">Crear cuenta gratis</Link>
        </div>
        <p className="trusted">Te Ofrecemos</p>
        
        <div className="brands">
          <img src={seguridad} alt="Seguridad" />
          <img src={rapidez} alt="Rapidez" />
          <img src={controlTotal} alt="Control Total" />
        </div>
      </div>

      {/* Tarjeta 3D */}
      <div className="hero-image" id="card-parallax">
        <div className="card-container">
          <div className="card-glow"></div>
          <div className="futuristic-lines"></div>
          {/* Generamos partículas dinámicamente */}
          {[...Array(8)].map((_, i) => <div key={i} className="particle"></div>)}

          <div className="credit-card">
            <div className="card-brand">VISA</div>
            <div className="card-chip"></div>
            <div className="card-number">4532 7890 1234 5678</div>
            <div className="card-details">
              <div>
                <div className="card-holder-label">Titular</div>
                <div className="card-holder-name">Paysub Pro</div>
              </div>
              <div>
                <div className="card-expiry-label">Expira</div>
                <div className="card-expiry-date">12/28</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
};

export default Hero;