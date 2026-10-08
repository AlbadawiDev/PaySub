# PaySub Web

Frontend React/Vite de PaySub. Consulta el README de la raíz para preparación, cuentas ficticias, demostración y limitaciones de pagos.

Comandos: `npm ci`, `npm run dev -- --host 127.0.0.1 --port 5175`, `npm run build`, `npm run lint -- --max-warnings=0`, `npm test`, `npm audit`.

El API predeterminado es `http://127.0.0.1:8012/api`; personalízalo con `VITE_API_BASE_URL`. Se almacenan tokens Sanctum en el almacenamiento local del navegador; antes de producción se debe evaluar una sesión con cookie HttpOnly y política CSP para reducir el impacto de XSS.
