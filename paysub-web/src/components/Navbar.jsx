import React from 'react';
import { Link } from 'react-router-dom';
// Asegúrate de mover tu logo a la carpeta src/assets
// import logo from '../assets/logo.png';

const Navbar = () => {
  return (
    <header className="navbar">
      <div className="logo">
        <Link to="/">
          {/* <img src="/logo.png" alt="PaySub" /> */}
          <span>PaySub</span>
        </Link>
      </div>

      <nav>
        <a href="#" className="btn-inicio">Inicio</a>
        <a href="#nosotros" className="btn-nosotros">Problema</a>
        <a href="#servicios" className="btn-servicios">Solución</a>
      </nav>

      <Link className="btn-signup" to="/login">Iniciar Sesión</Link>
    </header>
  );
};

export default Navbar;