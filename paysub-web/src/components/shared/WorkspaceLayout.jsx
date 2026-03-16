import React from 'react';
import { getUserInitials } from '../../utils/support';
import './WorkspaceLayout.css';

export function WorkspaceBanner({ tone = 'info', title, message }) {
  if (!message) {
    return null;
  }

  return (
    <div className={`workspace-banner workspace-banner--${tone}`}>
      {title && <strong>{title}</strong>}
      <p>{message}</p>
    </div>
  );
}

export function WorkspaceMetricCard({ label, value, hint, tone = 'default' }) {
  return (
    <article className={`workspace-metric workspace-metric--${tone}`}>
      <span>{label}</span>
      <strong>{value}</strong>
      {hint && <small>{hint}</small>}
    </article>
  );
}

export function WorkspaceState({ title, message, actionLabel, onAction }) {
  return (
    <div className="workspace-state">
      <h3>{title}</h3>
      <p>{message}</p>
      {actionLabel && onAction && (
        <button type="button" className="workspace-button workspace-button--ghost" onClick={onAction}>
          {actionLabel}
        </button>
      )}
    </div>
  );
}

export function WorkspacePill({ label, tone = 'neutral' }) {
  return <span className={`workspace-pill workspace-pill--${tone}`}>{label}</span>;
}

const WorkspaceLayout = ({
  brand,
  productLabel,
  user,
  sections,
  activeSection,
  onSectionChange,
  onLogout,
  title,
  subtitle,
  banner,
  children,
}) => {
  return (
    <div className="workspace-shell">
      <div className="workspace-shell__glow workspace-shell__glow--one"></div>
      <div className="workspace-shell__glow workspace-shell__glow--two"></div>

      <header className="workspace-topbar">
        <div className="workspace-brand">
          <div className="workspace-brand__mark">PS</div>
          <div>
            <strong>{brand}</strong>
            <span>{productLabel}</span>
          </div>
        </div>

        <div className="workspace-topbar__actions">
          <div className="workspace-user">
            <div className="workspace-user__avatar">{getUserInitials(user)}</div>
            <div className="workspace-user__copy">
              <strong>{[user?.nombre, user?.apellido].filter(Boolean).join(' ') || 'PaySub'}</strong>
              <span>{user?.correo_electronico || 'Cuenta autenticada'}</span>
            </div>
          </div>
          <button type="button" className="workspace-button workspace-button--ghost" onClick={onLogout}>
            Cerrar sesion
          </button>
        </div>
      </header>

      <div className="workspace-layout">
        <aside className="workspace-sidebar">
          <div className="workspace-sidebar__header">
            <span className="workspace-sidebar__eyebrow">Centro operativo</span>
            <h1>{title}</h1>
            <p>{subtitle}</p>
          </div>

          <nav className="workspace-nav">
            {sections.map((section) => (
              <button
                key={section.key}
                type="button"
                className={`workspace-nav__item ${activeSection === section.key ? 'is-active' : ''}`}
                onClick={() => onSectionChange(section.key)}
              >
                <span>{section.label}</span>
                {section.caption && <small>{section.caption}</small>}
              </button>
            ))}
          </nav>
        </aside>

        <main className="workspace-main">
          {banner && <WorkspaceBanner tone={banner.tone} title={banner.title} message={banner.message} />}
          {children}
        </main>
      </div>
    </div>
  );
};

export default WorkspaceLayout;
