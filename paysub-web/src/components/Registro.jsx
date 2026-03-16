import React, { useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import '../Registro.css';
import {
  clearPendingRegistration,
  consumeLoginPrefill,
  loadPendingRegistration,
  saveLoginPrefill,
  savePendingRegistration,
  saveVerifiedRegistration,
} from '../utils/authFlowStorage';

import { API_BASE_URL } from '../config/api';
const OTP_LENGTH = 6;

const createEmptyOtp = () => Array.from({ length: OTP_LENGTH }, () => '');

const maskEmail = (email) => {
  if (!email || !email.includes('@')) {
    return email;
  }

  const [localPart, domain] = email.split('@');
  const visible = localPart.slice(0, Math.min(2, localPart.length));

  return `${visible}***@${domain}`;
};

const extractApiError = (payload, fallbackMessage) => {
  if (!payload) {
    return fallbackMessage;
  }

  if (payload.errors) {
    return Object.values(payload.errors).flat().join(' ');
  }

  return payload.mensaje || payload.error || fallbackMessage;
};

const formatCountdown = (totalSeconds) => {
  const safeSeconds = Math.max(0, totalSeconds);
  const minutes = String(Math.floor(safeSeconds / 60)).padStart(2, '0');
  const seconds = String(safeSeconds % 60).padStart(2, '0');

  return `${minutes}:${seconds}`;
};

const Registro = () => {
  const navigate = useNavigate();
  const otpRefs = useRef([]);
  const [selectedUser, setSelectedUser] = useState(null);
  const [pendingVerification, setPendingVerification] = useState(null);
  const [otpDigits, setOtpDigits] = useState(createEmptyOtp());
  const [feedback, setFeedback] = useState(null);
  const [loadingState, setLoadingState] = useState({
    submit: false,
    verify: false,
    resend: false,
  });
  const [timeNow, setTimeNow] = useState(Date.now());
  const [formData, setFormData] = useState({
    nombre: '',
    apellido: '',
    cedula: '',
    rif: '',
    nombre_comercio: '',
    email: '',
    telefono: '',
    password: '',
    web: '',
  });

  useEffect(() => {
    const storedPending = loadPendingRegistration();
    const loginPrefill = consumeLoginPrefill();

    if (storedPending?.email) {
      setPendingVerification(storedPending);
      setSelectedUser(storedPending.userType ?? null);
      setFormData((current) => ({
        ...current,
        email: storedPending.email,
      }));
    }

    if (loginPrefill) {
      setFormData((current) => ({
        ...current,
        email: loginPrefill,
      }));
    }
  }, []);

  useEffect(() => {
    const timerId = window.setInterval(() => {
      setTimeNow(Date.now());
    }, 1000);

    return () => window.clearInterval(timerId);
  }, []);

  useEffect(() => {
    if (pendingVerification) {
      savePendingRegistration(pendingVerification);
    }
  }, [pendingVerification]);

  const secondsUntilResend = useMemo(() => {
    if (!pendingVerification?.resendAvailableAt) {
      return 0;
    }

    return Math.max(
      0,
      Math.ceil((new Date(pendingVerification.resendAvailableAt).getTime() - timeNow) / 1000)
    );
  }, [pendingVerification, timeNow]);

  const secondsUntilExpiration = useMemo(() => {
    if (!pendingVerification?.expiresAt) {
      return null;
    }

    return Math.max(
      0,
      Math.ceil((new Date(pendingVerification.expiresAt).getTime() - timeNow) / 1000)
    );
  }, [pendingVerification, timeNow]);

  const canResend = Boolean(
    pendingVerification &&
    !loadingState.resend &&
    secondsUntilResend === 0 &&
    (pendingVerification.resendsLeft === undefined || pendingVerification.resendsLeft > 0)
  );

  const currentStep = pendingVerification ? 'otp' : selectedUser ? 'form' : 'select';

  const handleUserSelect = (userType) => {
    setFeedback(null);
    setSelectedUser(userType);
  };

  const handleInputChange = (event) => {
    setFormData((current) => ({
      ...current,
      [event.target.name]: event.target.value,
    }));
  };

  const updatePendingVerification = (email, userType, responseData) => {
    const nextState = {
      email,
      emailMasked: responseData.email_masked || maskEmail(email),
      userType,
      expiresAt: responseData.expires_in
        ? new Date(Date.now() + responseData.expires_in * 1000).toISOString()
        : null,
      resendAvailableAt: responseData.resend_in
        ? new Date(Date.now() + responseData.resend_in * 1000).toISOString()
        : null,
      resendsLeft: responseData.resends_left,
    };

    setPendingVerification(nextState);
    setOtpDigits(createEmptyOtp());
    setFeedback({
      type: 'success',
      title: 'Codigo enviado',
      message: `Revisa ${nextState.emailMasked}. Te enviamos un codigo de verificacion para continuar.`,
    });

    window.setTimeout(() => {
      otpRefs.current[0]?.focus();
    }, 50);
  };

  const handleSubmit = async (event) => {
    event.preventDefault();
    setLoadingState((current) => ({ ...current, submit: true }));
    setFeedback(null);

    const url = selectedUser === 'cliente'
      ? `${API_BASE_URL}/register-cliente`
      : `${API_BASE_URL}/register`;

    try {
      const response = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify(formData),
      });

      const data = await response.json().catch(() => ({}));

      if (!response.ok) {
        throw new Error(extractApiError(data, 'No fue posible iniciar el registro.'));
      }

      updatePendingVerification(formData.email, selectedUser, data.data);
    } catch (error) {
      setFeedback({
        type: 'error',
        title: 'No pudimos continuar',
        message: error.message,
      });
    } finally {
      setLoadingState((current) => ({ ...current, submit: false }));
    }
  };

  const handleOtpChange = (index, rawValue) => {
    const sanitizedValue = rawValue.replace(/\D/g, '').slice(-1);
    const nextDigits = [...otpDigits];
    nextDigits[index] = sanitizedValue;
    setOtpDigits(nextDigits);

    if (sanitizedValue && index < OTP_LENGTH - 1) {
      otpRefs.current[index + 1]?.focus();
    }
  };

  const handleOtpKeyDown = (index, event) => {
    if (event.key === 'Backspace' && !otpDigits[index] && index > 0) {
      otpRefs.current[index - 1]?.focus();
    }

    if (event.key === 'ArrowLeft' && index > 0) {
      otpRefs.current[index - 1]?.focus();
    }

    if (event.key === 'ArrowRight' && index < OTP_LENGTH - 1) {
      otpRefs.current[index + 1]?.focus();
    }
  };

  const handleOtpPaste = (event) => {
    const pasted = event.clipboardData.getData('text').replace(/\D/g, '').slice(0, OTP_LENGTH);

    if (!pasted) {
      return;
    }

    event.preventDefault();
    const nextDigits = createEmptyOtp();
    pasted.split('').forEach((digit, index) => {
      nextDigits[index] = digit;
    });
    setOtpDigits(nextDigits);
    otpRefs.current[Math.min(pasted.length, OTP_LENGTH) - 1]?.focus();
  };

  const handleVerifyOtp = async (event) => {
    event.preventDefault();
    const otp = otpDigits.join('');

    if (otp.length !== OTP_LENGTH || !pendingVerification?.email) {
      setFeedback({
        type: 'error',
        title: 'Codigo incompleto',
        message: 'Ingresa los 6 digitos del codigo para continuar.',
      });
      return;
    }

    setLoadingState((current) => ({ ...current, verify: true }));
    setFeedback(null);

    try {
      const response = await fetch(`${API_BASE_URL}/register/verify-otp`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify({
          email: pendingVerification.email,
          otp,
        }),
      });

      const data = await response.json().catch(() => ({}));

      if (!response.ok) {
        throw new Error(extractApiError(data, 'No fue posible verificar el codigo.'));
      }

      clearPendingRegistration();
      saveVerifiedRegistration(pendingVerification.email);
      saveLoginPrefill(pendingVerification.email);
      setPendingVerification(null);
      setOtpDigits(createEmptyOtp());
      setFeedback({
        type: 'success',
        title: 'Correo verificado',
        message: 'Tu cuenta ya esta activa. Te llevaremos al inicio de sesion.',
      });

      window.setTimeout(() => {
        navigate('/login');
      }, 1200);
    } catch (error) {
      setFeedback({
        type: 'error',
        title: 'Verificacion fallida',
        message: error.message,
      });
    } finally {
      setLoadingState((current) => ({ ...current, verify: false }));
    }
  };

  const handleResendOtp = async () => {
    if (!pendingVerification?.email || !canResend) {
      return;
    }

    setLoadingState((current) => ({ ...current, resend: true }));
    setFeedback(null);

    try {
      const response = await fetch(`${API_BASE_URL}/register/resend-otp`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify({ email: pendingVerification.email }),
      });

      const data = await response.json().catch(() => ({}));

      if (!response.ok) {
        throw new Error(extractApiError(data, 'No fue posible reenviar el codigo.'));
      }

      updatePendingVerification(
        pendingVerification.email,
        pendingVerification.userType,
        data.data
      );
      setFeedback({
        type: 'success',
        title: 'Codigo reenviado',
        message: `Enviamos un nuevo codigo a ${data.data.email_masked || maskEmail(pendingVerification.email)}.`,
      });
    } catch (error) {
      setFeedback({
        type: 'error',
        title: 'No se pudo reenviar',
        message: error.message,
      });
    } finally {
      setLoadingState((current) => ({ ...current, resend: false }));
    }
  };

  const handleBackAction = () => {
    if (pendingVerification) {
      clearPendingRegistration();
      setPendingVerification(null);
      setOtpDigits(createEmptyOtp());
      setFeedback(null);
      return;
    }

    if (selectedUser) {
      setSelectedUser(null);
      setFeedback(null);
      return;
    }

    navigate(-1);
  };

  return (
    <div className="register-shell">
      <div className="register-shell__gradient register-shell__gradient--top"></div>
      <div className="register-shell__gradient register-shell__gradient--bottom"></div>
      <div className="register-shell__particles">
        {Array.from({ length: 18 }, (_, index) => (
          <span
            key={index}
            className="register-shell__particle"
            style={{
              '--delay': `${index * 0.55}s`,
              '--duration': `${9 + (index % 5)}s`,
            }}
          ></span>
        ))}
      </div>

      <header className="register-nav">
        <button type="button" className="register-nav__brand" onClick={() => navigate('/')}>
          PaySub
        </button>
        <button type="button" onClick={handleBackAction} className="register-nav__back">
          Volver
        </button>
      </header>

      <main className="register-layout">
        <section className="register-showcase">
          <div className="register-showcase__eyebrow">Onboarding verificado</div>
          <h1 className="register-showcase__title">
            Registro elegante para clientes y comercios.
          </h1>
          <p className="register-showcase__description">
            Activa cuentas con verificacion por codigo, reduce registros incompletos y mantén un flujo claro desde el primer contacto.
          </p>

          <div className="register-showcase__cards">
            <article className="register-showcase__card">
              <span className="register-showcase__card-label">Seguridad</span>
              <strong>Verificacion OTP de 6 digitos</strong>
              <p>Envio por correo, expiracion controlada y reintentos limitados.</p>
            </article>

            <article className="register-showcase__card">
              <span className="register-showcase__card-label">Experiencia</span>
              <strong>Flujo de 2 pasos sin friccion</strong>
              <p>Formulario claro, estados visuales consistentes y continuidad entre pantallas.</p>
            </article>

            <article className="register-showcase__card">
              <span className="register-showcase__card-label">Activacion</span>
              <strong>Cuenta lista para iniciar sesion</strong>
              <p>El usuario verifica, activa el correo y entra al flujo normal con Sanctum.</p>
            </article>
          </div>
        </section>

        <section className="register-panel">
          <div className="register-card">
            <div className="register-card__topbar">
              <div>
                <span className="register-card__badge">Registro PaySub</span>
                <h2 className="register-card__title">
                  {currentStep === 'otp'
                    ? 'Verifica tu correo'
                    : selectedUser === 'comercio'
                      ? 'Crea tu cuenta de comercio'
                      : selectedUser === 'cliente'
                        ? 'Crea tu cuenta de cliente'
                        : 'Empieza tu onboarding'}
                </h2>
              </div>
              <div className="register-stepper">
                <div className={`register-stepper__item ${currentStep !== 'select' ? 'is-active' : ''}`}>
                  <span>1</span>
                  <small>Datos</small>
                </div>
                <div className={`register-stepper__item ${currentStep === 'otp' ? 'is-active' : ''}`}>
                  <span>2</span>
                  <small>Codigo</small>
                </div>
              </div>
            </div>

            <p className="register-card__subtitle">
              {currentStep === 'otp'
                ? 'Introduce el codigo que enviamos para activar tu cuenta.'
                : currentStep === 'form'
                  ? 'Completa los datos esenciales y te enviaremos un codigo OTP.'
                  : 'Selecciona el tipo de cuenta que deseas abrir.'}
            </p>

            {feedback && (
              <div className={`register-feedback register-feedback--${feedback.type}`}>
                <strong>{feedback.title}</strong>
                <p>{feedback.message}</p>
              </div>
            )}

            {currentStep === 'select' && (
              <div className="register-role-grid">
                <button type="button" className="register-role-card" onClick={() => handleUserSelect('comercio')}>
                  <span className="register-role-card__accent"></span>
                  <h3>Comercio</h3>
                  <p>Crea planes, recibe pagos y administra tus suscripciones desde un solo panel.</p>
                  <span className="register-role-card__cta">Continuar como comercio</span>
                </button>

                <button type="button" className="register-role-card" onClick={() => handleUserSelect('cliente')}>
                  <span className="register-role-card__accent"></span>
                  <h3>Cliente</h3>
                  <p>Centraliza tus suscripciones, pagos y comprobantes con una experiencia clara.</p>
                  <span className="register-role-card__cta">Continuar como cliente</span>
                </button>
              </div>
            )}

            {currentStep === 'form' && (
              <form onSubmit={handleSubmit} className="register-form">
                {selectedUser === 'cliente' && (
                  <div className="register-form__grid register-form__grid--triple">
                    <label className="register-field">
                      <span>Nombre</span>
                      <input
                        type="text"
                        name="nombre"
                        value={formData.nombre}
                        onChange={handleInputChange}
                        placeholder="Ej. Juan"
                        required
                      />
                    </label>

                    <label className="register-field">
                      <span>Apellido</span>
                      <input
                        type="text"
                        name="apellido"
                        value={formData.apellido}
                        onChange={handleInputChange}
                        placeholder="Ej. Perez"
                        required
                      />
                    </label>

                    <label className="register-field">
                      <span>Cedula</span>
                      <input
                        type="text"
                        name="cedula"
                        value={formData.cedula}
                        onChange={handleInputChange}
                        placeholder="V-12345678"
                        required
                      />
                    </label>
                  </div>
                )}

                {selectedUser === 'comercio' && (
                  <div className="register-form__grid register-form__grid--double">
                    <label className="register-field">
                      <span>Nombre del comercio</span>
                      <input
                        type="text"
                        name="nombre_comercio"
                        value={formData.nombre_comercio}
                        onChange={handleInputChange}
                        placeholder="Ej. Gimnasio Titan"
                        required
                      />
                    </label>

                    <label className="register-field">
                      <span>RIF</span>
                      <input
                        type="text"
                        name="rif"
                        value={formData.rif}
                        onChange={handleInputChange}
                        placeholder="J-12345678-9"
                        required
                      />
                    </label>
                  </div>
                )}

                <div className="register-form__grid register-form__grid--double">
                  <label className="register-field">
                    <span>Correo electronico</span>
                    <input
                      type="email"
                      name="email"
                      value={formData.email}
                      onChange={handleInputChange}
                      placeholder="ejemplo@correo.com"
                      required
                    />
                  </label>

                  <label className="register-field">
                    <span>Telefono</span>
                    <input
                      type="tel"
                      name="telefono"
                      value={formData.telefono}
                      onChange={handleInputChange}
                      placeholder="0414-0000000"
                    />
                  </label>
                </div>

                <div className="register-form__grid register-form__grid--double">
                  <label className="register-field">
                    <span>Contrasena</span>
                    <input
                      type="password"
                      name="password"
                      value={formData.password}
                      onChange={handleInputChange}
                      placeholder="Minimo 6 caracteres"
                      required
                    />
                  </label>

                  {selectedUser === 'comercio' && (
                    <label className="register-field">
                      <span>Sitio web</span>
                      <input
                        type="url"
                        name="web"
                        value={formData.web}
                        onChange={handleInputChange}
                        placeholder="https://tucomercio.com"
                      />
                    </label>
                  )}
                </div>

                <button type="submit" className="register-primary-button" disabled={loadingState.submit}>
                  {loadingState.submit ? 'Enviando codigo...' : 'Continuar con verificacion'}
                </button>

                <p className="register-form__hint">
                  Al continuar, enviaremos un codigo OTP a tu correo para activar la cuenta.
                </p>
              </form>
            )}

            {currentStep === 'otp' && (
              <form onSubmit={handleVerifyOtp} className="register-otp">
                <div className="register-otp__card">
                  <span className="register-otp__label">Correo de verificacion</span>
                  <strong>{pendingVerification?.emailMasked || maskEmail(formData.email)}</strong>
                  <p>
                    Introduce el codigo de 6 digitos. Si no lo encuentras, revisa spam o solicita uno nuevo.
                  </p>
                </div>

                <div className="register-otp__inputs" onPaste={handleOtpPaste}>
                  {otpDigits.map((digit, index) => (
                    <input
                      key={index}
                      ref={(element) => {
                        otpRefs.current[index] = element;
                      }}
                      className="register-otp__input"
                      inputMode="numeric"
                      autoComplete="one-time-code"
                      maxLength={1}
                      value={digit}
                      onChange={(event) => handleOtpChange(index, event.target.value)}
                      onKeyDown={(event) => handleOtpKeyDown(index, event)}
                    />
                  ))}
                </div>

                <div className="register-otp__status">
                  <div>
                    <span className="register-otp__status-label">Expira en</span>
                    <strong>{secondsUntilExpiration === null ? '--:--' : formatCountdown(secondsUntilExpiration)}</strong>
                  </div>
                  <div>
                    <span className="register-otp__status-label">Reenviar</span>
                    <strong>{formatCountdown(secondsUntilResend)}</strong>
                  </div>
                </div>

                <button type="submit" className="register-primary-button" disabled={loadingState.verify}>
                  {loadingState.verify ? 'Verificando...' : 'Verificar codigo'}
                </button>

                <div className="register-otp__actions">
                  <button
                    type="button"
                    className="register-secondary-button"
                    onClick={handleResendOtp}
                    disabled={!canResend}
                  >
                    {loadingState.resend
                      ? 'Reenviando...'
                      : canResend
                        ? 'Reenviar codigo'
                        : pendingVerification?.resendsLeft === 0
                          ? 'Limite de reenvios alcanzado'
                          : `Disponible en ${formatCountdown(secondsUntilResend)}`}
                  </button>

                  <button type="button" className="register-link-button" onClick={handleBackAction}>
                    Cambiar correo o editar datos
                  </button>
                </div>
              </form>
            )}
          </div>
        </section>
      </main>
    </div>
  );
};

export default Registro;
