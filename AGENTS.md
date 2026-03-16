# PaySub System

## Stack
- Backend: Laravel
- Frontend: React + Vite
- Base de datos: PostgreSQL
- Auth: Laravel Sanctum

## Objetivo
Completar y modernizar el sistema existente sin rehacer lo que ya funciona.

## Prioridades
1. Registro con verificación por correo usando código OTP
2. Módulo de reclamos del cliente
3. Métricas globales del administrador
4. Mejoras visuales modernas
5. Validaciones y pruebas

## Reglas
- No romper rutas existentes
- No subir secretos ni credenciales
- Usar migraciones para cambios de base de datos
- Mantener roles de cliente, comercio y administrador
- Mantener compatibilidad con Sanctum

## Comandos
- composer install
- php artisan migrate
- php artisan serve
- npm install
- npm run dev
- php artisan test
