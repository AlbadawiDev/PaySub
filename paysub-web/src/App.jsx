import React from 'react';
import { Routes, Route, Navigate } from 'react-router-dom';
import Landing from './components/Landing';
import Login from './components/Login';
import Registro from './components/Registro';
import ClienteDashboard from './components/ClienteDashboard';
// IMPORTANTE: Importamos el componente desde su propio archivo
import ComercioDashboard from './components/ComercioDashboard'; 

function App() {
  return (
    <Routes>
      {/* Ruta Principal */}
      <Route path="/" element={<Landing />} />
      
      {/* Autenticación */}
      <Route path="/login" element={<Login />} />
      <Route path="/registro" element={<Registro />} />
      
      {/* Dashboards Reales */}
      <Route path="/cliente-dashboard" element={<ClienteDashboard />} />
      <Route path="/comercio/dashboard" element={<ComercioDashboard />} />
      
      {/* Redirección por defecto si la ruta no existe (404) */}
      <Route path="*" element={<Navigate to="/" />} />
    </Routes>
  );
}

export default App;