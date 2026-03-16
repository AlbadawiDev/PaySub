import React from 'react';
import { Navigate, Route, Routes } from 'react-router-dom';
import Landing from './components/Landing';
import Login from './components/Login';
import Registro from './components/Registro';
import ClienteDashboard from './components/ClienteDashboard';
import ComercioDashboard from './components/ComercioDashboard';

function ProtectedRoute({ element, allowedRoles = [] }) {
  const token = localStorage.getItem('access_token');
  const userType = localStorage.getItem('user_type');

  if (!token) {
    return <Navigate to="/login" replace />;
  }

  if (allowedRoles.length > 0 && !allowedRoles.includes(userType)) {
    if (userType === 'comercio') {
      return <Navigate to="/comercio/dashboard" replace />;
    }

    if (userType === 'cliente') {
      return <Navigate to="/cliente-dashboard" replace />;
    }

    return <Navigate to="/login" replace />;
  }

  return element;
}

function App() {
  return (
    <Routes>
      <Route path="/" element={<Landing />} />
      <Route path="/login" element={<Login />} />
      <Route path="/registro" element={<Registro />} />

      <Route
        path="/cliente-dashboard"
        element={<ProtectedRoute element={<ClienteDashboard />} allowedRoles={['cliente']} />}
      />
      <Route
        path="/comercio/dashboard"
        element={<ProtectedRoute element={<ComercioDashboard />} allowedRoles={['comercio']} />}
      />

      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}

export default App;
