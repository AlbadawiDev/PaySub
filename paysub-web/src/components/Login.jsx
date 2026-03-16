import React, { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import '../Login.css';
import {
  clearPendingRegistration,
  consumeLoginPrefill,
  consumeVerifiedRegistration,
  loadPendingRegistration,
  savePendingRegistration,
} from '../utils/authFlowStorage';

import { API_BASE_URL } from '../config/api';

const extractApiError = (payload, fallbackMessage) => {
  if (!payload) {
    return fallbackMessage;
  }

  if (payload.errors) {
    return Object.values(payload.errors).flat().join(' ');
  }

  return payload.mensaje || payload.error || fallbackMessage;
};

const Login = () => {
  const navigate = useNavigate();
  const [formData, setFormData] = useState({ email: '', password: '' });
  const [loading, setLoading] = useState(false);
  const [feedback, setFeedback] = useState(null);
  const [pendingEmail, setPendingEmail] = useState(null);

  useEffect(() => {
    localStorage.removeItem('access_token');
    localStorage.removeItem('user_type');

    const verifiedEmail = consumeVerifiedRegistration();
    const loginPrefill = consumeLoginPrefill();
    const storedPending = loadPendingRegistration();

    if (verifiedEmail) {
      setFeedback({
        type: 'success',
        title: 'Correo verificado',
        message: 'Tu cuenta ya esta activa. Inicia sesion para continuar en PaySub.',
      });
    }

    if (loginPrefill) {
      setFormData((current) => ({
        ...current,
        email: loginPrefill,
      }));
    } else if (storedPending?.email) {
      setFormData((current) => ({
        ...current,
        email: storedPending.email,
      }));
    }

    if (storedPending?.email) {
      setPendingEmail(storedPending.email);
    }
  }, []);

  const handleChange = (event) => {
    setFormData((current) => ({
      ...current,
      [event.target.name]: event.target.value,
    }));
  };

  const handleContinueVerification = () => {
    if (!pendingEmail && !formData.email) {
      return;
    }

    savePendingRegistration({
      email: pendingEmail || formData.email,
      emailMasked: null,
      userType: null,
      expiresAt: null,
      resendAvailableAt: null,
      resendsLeft: undefined,
    });
    navigate('/registro');
  };

  const handleSubmit = async (event) => {
    event.preventDefault();
    setLoading(true);
    setFeedback(null);

    try {
      const response = await fetch(`${API_BASE_URL}/login`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify({
          email: formData.email,
          password: formData.password,
        }),
      });

      const data = await response.json().catch(() => ({}));

      if (!response.ok) {
        if (response.status === 403 && data.code === 'EMAIL_NOT_VERIFIED') {
          setPendingEmail(data.data?.email || formData.email);
          savePendingRegistration({
            email: data.data?.email || formData.email,
            emailMasked: null,
            userType: null,
            expiresAt: null,
            resendAvailableAt: null,
            resendsLeft: undefined,
          });
          setFeedback({
            type: 'warning',
            title: 'Correo pendiente de verificacion',
            message: 'Debes verificar tu correo antes de iniciar sesion. Puedes retomar el flujo ahora mismo.',
          });
          return;
        }

        throw new Error(extractApiError(data, 'No fue posible iniciar sesion.'));
      }

      clearPendingRegistration();
      localStorage.setItem('access_token', data.access_token);
      localStorage.setItem('user_type', data.user.tipo_usuario);

      if (data.user.tipo_usuario === 'comercio') {
        navigate('/comercio/dashboard');
      } else {
        navigate('/cliente-dashboard');
      }
    } catch (error) {
      setFeedback({
        type: 'error',
        title: 'Inicio de sesion fallido',
        message: error.message,
      });
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="login-shell">
      <div className="login-shell__gradient login-shell__gradient--top"></div>
      <div className="login-shell__gradient login-shell__gradient--bottom"></div>

      <header className="login-nav">
        <button type="button" className="login-nav__brand" onClick={() => navigate('/')}>
          PaySub
        </button>
        <Link to="/" className="login-nav__back">Volver</Link>
      </header>

      <main className="login-layout">
        <section className="login-panel">
          <div className="login-card">
            <span className="login-card__badge">Acceso seguro</span>
            <h1 className="login-card__title">Inicia sesion y retoma tu operacion.</h1>
            <p className="login-card__subtitle">
              Entra con tu cuenta verificada y continúa con el flujo normal de PaySub.
            </p>

            {feedback && (
              <div className={`login-feedback login-feedback--${feedback.type}`}>
                <strong>{feedback.title}</strong>
                <p>{feedback.message}</p>
                {feedback.type === 'warning' && (
                  <button type="button" className="login-feedback__action" onClick={handleContinueVerification}>
                    Continuar verificacion
                  </button>
                )}
              </div>
            )}

            <form onSubmit={handleSubmit} className="login-form">
              <label className="login-field">
                <span>Correo electronico</span>
                <input
                  type="email"
                  id="email"
                  name="email"
                  value={formData.email}
                  onChange={handleChange}
                  placeholder="ejemplo@correo.com"
                  required
                />
              </label>

              <label className="login-field">
                <span>Contrasena</span>
                <input
                  type="password"
                  id="password"
                  name="password"
                  value={formData.password}
                  onChange={handleChange}
                  placeholder="Tu contrasena"
                  required
                />
              </label>

              <button type="submit" className="login-primary-button" disabled={loading}>
                {loading ? 'Ingresando...' : 'Ingresar a PaySub'}
              </button>
            </form>

            <div className="login-footer">
              <p>¿Aún no tienes cuenta? <Link to="/registro">Regístrate aquí</Link></p>
            </div>
          </div>
        </section>

        <section className="login-showcase">
          <div className="login-showcase__badge">Diseño SaaS moderno</div>
          <h2 className="login-showcase__title">Controla accesos y verificacion sin friccion.</h2>
          <p className="login-showcase__description">
            Login y registro ahora comparten una experiencia visual clara, con mensajes precisos, continuidad entre pasos y reactivacion rápida de cuentas pendientes.
          </p>

          <div className="login-showcase__grid">
            <article className="login-showcase__card">
              <span>Verificacion</span>
              <strong>Reanudacion inmediata del OTP</strong>
              <p>Si la cuenta no está verificada, el login te lleva de vuelta al flujo correcto.</p>
            </article>

            <article className="login-showcase__card">
              <span>Feedback</span>
              <strong>Estados claros de carga y error</strong>
              <p>El usuario entiende qué pasó y cuál es el siguiente paso en todo momento.</p>
            </article>

            <article className="login-showcase__card">
              <span>Consistencia</span>
              <strong>Interfaz profesional y responsive</strong>
              <p>Jerarquía visual limpia tanto en desktop como en móvil.</p>
            </article>
          </div>
        </section>
      </main>
    </div>
  );
};

export default Login;
