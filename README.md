# PaySub System 🚀

Bienvenido al repositorio central. Esta es la base profesional del sistema de pagos.

## 🛠️ Arquitectura Base
- **Backend:** Laravel 11 + Sanctum (Carpeta `paysub-api`).
- **Frontend:** React + Vite (Carpeta `paysub-web`).
- **Seguridad:** Multi-tenancy implementado con `BaseTenantModel`.

## 📌 Instrucciones para el Equipo
1. **Ramas:** No trabajen en `main`. Siempre usen `develop`.
2. **Nuevas Tareas:** Creen una rama propia: `git checkout -b feature/nombre-de-su-tarea`.
3. **Modelos:** Todos los modelos nuevos deben extender de `BaseTenantModel`.
4. **Base de Datos:** Configurar PostgreSQL con el nombre `paysub_db`.