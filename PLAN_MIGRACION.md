# Plan de reconstrucción y migración — sisTransporte → Laravel multi-tenant

> Documento vivo. Se ajusta a medida que avanzamos; cada cambio de decisión se anota en el
> [registro de decisiones](#registro-de-decisiones).
>
> **Estado:** planificación. Ninguna fase ha iniciado.

---

## 1. Contexto

- **Sistema legacy:** `sisTransporte/` (PHP sin framework + MySQL 8, una sola empresa: Transportes Kemuel S.A.S.).
  Es solo referencia: **no se modifica nada dentro de esa carpeta.**
- **Sistema nuevo:** este proyecto. Laravel 12, Inertia v3 + Vue 3 + TypeScript, PostgreSQL,
  multi-tenant **single-database** (`stancl/tenancy`, columna `tenant_id`).
- **Objetivo:** reconstruir la lógica de negocio con un esquema normalizado y, en paralelo,
  construir un sistema de migración de datos operable desde el panel del administrador central.

## 2. Principios

1. **Primero se construye, después se migra.** Ningún dato legacy entra al sistema hasta que
   el módulo correspondiente exista, tenga tests y su importador esté validado.
2. **La migración se diseña desde el día uno.** Cada módulo que se construye entrega también
   su importador (ver [§5](#5-sistema-de-migración-de-datos)).
3. **Aislamiento por tenant en la base de datos.** Toda tabla de negocio tiene `tenant_id NOT NULL`
   y FK compuestas `(tenant_id, id)`: un registro no puede apuntar a datos de otra empresa.
4. **Autorización por permisos, nunca por rol.** `can()`, Policies y middleware `permission:`.
   Ningún `if rol == X` en el código.
5. **Idioma:** código, tablas y columnas en inglés; interfaz en español.
6. **Convenciones Laravel:** Form Requests, Policies, Enums para estados, Actions/servicios para
   la lógica, tests de Pest en cada módulo.

## 3. Arquitectura: dos mundos

| | Panel central (landlord) | Aplicación del tenant |
|---|---|---|
| Dominio | `sistema-transporte-tenant.test` (dominios centrales) | `{empresa}.sistema-transporte-tenant.test` |
| Usuarios | Administradores de la plataforma (tabla y guard propios, sin `tenant_id`) | Usuarios de cada empresa (`users` con `tenant_id`) |
| Funciones | Crear/gestionar inquilinos, dominios, planes; **ejecutar migraciones**; monitoreo | Operación del negocio: órdenes, FUEC, flota, conductores… |
| Rutas | `routes/web.php` (+ `routes/central.php`) | `routes/tenant.php` |

> Hoy el login y el registro solo existen en el tenant. El panel central (con su propio login)
> se construye en la Fase 0.

---

## 4. Fases

Leyenda: ⬜ pendiente · 🟨 en curso · ✅ terminado

### Fase 0 — Panel central y gestión de inquilinos ⬜
- ⬜ Modelo y guard de **administradores centrales** (tabla `central_users`, login propio en el dominio central).
- ⬜ CRUD de **inquilinos**: nombre, NIT, estado (activo/suspendido), datos de la empresa.
- ⬜ Gestión de **dominios** por inquilino (subdominio principal y dominios adicionales).
- ⬜ **Alta de inquilino**: al crearlo se siembran sus roles base y se crea su primer usuario Administrador (invitación por correo).
- ⬜ Suspender / reactivar inquilino (bloquea el acceso sin borrar datos).
- ⬜ Esqueleto del **módulo de migraciones** en el panel (ver §5): pantallas y tablas de control, aún sin importadores.

### Fase 1 — Identidad, roles y permisos (por tenant) ⬜
- ⬜ Instalar `spatie/laravel-permission` en modo *teams* (team = `tenant_id`, tipo string/UUID).
- ⬜ **Catálogo de permisos** en un Enum (`recurso.accion`: `users.view`, `roles.manage`, `service-orders.create`…), sincronizado por seeder.
- ⬜ **Roles base** sembrados por tenant: Administrador (todos los permisos), Administrativo, Coordinador, Asistente de coordinación, Conductor, Cliente, Proveedor, Aliado. Editables por cada empresa.
- ⬜ **Un rol por usuario** garantizado en 3 niveles: índice único en `model_has_roles`, servicio único de asignación (`syncRoles([$rol])`) y tests. Diseñado para permitir multi-rol más adelante quitando el índice.
- ⬜ **Usuario vinculado a un actor**: `employee_id` | `client_id` | `supplier_id` | `ally_id` (exactamente uno, con CHECK en BD). *Las columnas y FK se completan cuando existan esas tablas (Fase 2).*
- ⬜ **Login opción A**: un campo "usuario o correo". `users.username` obligatorio y único por tenant; `users.email` opcional y único por tenant. Ajustar login, perfil, recuperación de contraseña.
- ⬜ Pantallas: gestión de usuarios, gestión de roles y su matriz de permisos, menú filtrado por permisos, página "sin acceso asignado".
- ⬜ Importador: **usuarios + roles** (§5.4).

### Fase 2 — Módulos de negocio (por tenant) ⬜
Orden por dependencias. Cada módulo incluye: migraciones, modelos, Policies, pantallas, tests e **importador**.

| # | Módulo | Notas |
|---|---|---|
| 2.1 | Configuración de la empresa | Razón social, NIT, logo, datos de habilitación del FUEC |
| 2.2 | Terceros: aliados y proveedores | Entidades del tenant (no tenants). Relación aliado↔proveedor sin triggers |
| 2.3 | Clientes | Persona/empresa, sucursales; dueño: empresa o aliado |
| 2.4 | Rutas | |
| 2.5 | Empleados y conductores | "Conductor" es una función del empleado, no un rol. Documentos con vencimiento |
| 2.6 | Vehículos | Documentos, fotos, partes (catálogo en tabla), convenios |
| 2.7 | Contratos propios y contratos de aliados | Entidades separadas |
| 2.8 | **Órdenes de servicio** | Núcleo. Máquina de estados explícita, pasajeros, apoyo, observaciones, kilometrajes |
| 2.9 | **Órdenes de aliados** | **Dominio separado** de las órdenes propias (mismo esquema legacy, distinto negocio). Flujo de estados por definir |
| 2.10 | FUEC | Consecutivo por tenant con bloqueo, PDF, QR de verificación, correo |
| 2.11 | Operación del conductor | Servicios asignados, inicio/fin con km y foto, checklist normalizado (ítems + respuestas), combustible, novedades |
| 2.12 | Aprobaciones gerenciales | Cancelaciones y modificaciones de órdenes |
| 2.13 | Facturación y pagos | Cliente y proveedor, con trazabilidad de aprobación |
| 2.14 | Mantenimientos y alertas | Recordatorios por km, vencimientos de documentos (scheduler) |
| 2.15 | Notificaciones | Laravel Notifications (BD, correo y push); canal push por definir |
| 2.16 | Portales de terceros | Cliente, proveedor y aliado: permiso + Policy por entidad propia |
| 2.17 | Reportes y estadísticas | |
| 2.18 | GPS | Ingesta de posiciones (tabla particionada por mes) y mapa |

### Fase 3 — API móvil ⬜
- ⬜ Existe una app móvil que consume `api-transporte-kemuel`. Inventariar sus endpoints y contratos.
- ⬜ Definir estrategia: capa de compatibilidad con la API vieja vs. nueva API (Sanctum) + actualización de la app.

### Fase 4 — Migración y salida a producción (Kemuel) ⬜
- ⬜ Ensayos completos de migración sobre una copia, desde el panel central.
- ⬜ Conciliación aprobada (§5.6).
- ⬜ Ventana de corte: legacy en solo lectura → migración final → validación → apertura.
- ⬜ Periodo de convivencia con el legacy en solo lectura.

---

## 5. Sistema de migración de datos

### 5.1 Objetivo
Migrar los datos de un sistema legacy hacia **un inquilino concreto**, operado desde el panel
central, de forma **repetible, auditable y segura**. Aunque hoy solo existe Kemuel, el sistema
debe servir para cualquier empresa futura que llegue con su propia base legacy.

### 5.2 Flujo en el panel central
1. **Fuente:** el administrador registra una fuente legacy (conexión MySQL de solo lectura a una
   copia, o carga de un dump que se restaura en una base temporal). Las credenciales se guardan cifradas.
2. **Destino:** se elige el inquilino. El sistema verifica la identidad (p. ej. NIT de
   `configuracion_empresa` = NIT del inquilino) antes de permitir ejecutar.
3. **Pre-análisis (solo lectura):** conteos por tabla y chequeos de calidad (§5.6-A). Resultado
   descargable.
4. **Ejecución:** se eligen los módulos a migrar (respetando dependencias) y el modo:
   - *Simulación (dry-run):* corre todo dentro de transacciones que se revierten; solo produce reportes.
   - *Real:* escribe en el inquilino.
5. **Seguimiento:** progreso en vivo por paso (leídas / insertadas / actualizadas / cuarentena / omitidas), logs y errores.
6. **Resultado:** reporte de conciliación y lista de cuarentena; los registros en cuarentena se
   pueden corregir y reintentar.

### 5.3 Diseño técnico
- **Tablas de control (centrales):** `migration_sources`, `migration_runs` (inquilino, modo, estado,
  quién, cuándo), `migration_run_steps` (módulo, contadores, duración), `migration_issues`
  (cuarentena y errores, con referencia al registro legacy).
- **Ejecución en colas:** cada paso es un Job; una corrida es un `Bus::batch()` / cadena que respeta
  dependencias. Se puede pausar, cancelar y reanudar.
- **Importadores por módulo:** una clase por entidad con un contrato común
  (`dependsOn()`, `count()`, `extract()` por lotes con `lazyById`, `transform()`, `load()` por lotes con upsert).
- **Idempotencia y trazabilidad:** toda tabla migrada guarda `legacy_source` + `legacy_id`, con índice
  único `(tenant_id, legacy_source, legacy_id)`. Repetir una corrida actualiza, no duplica.
- **Fallar cerrado:** un valor sin mapeo (rol, estado, permiso, catálogo) envía el registro a
  cuarentena; nunca se asigna un valor "por defecto" que otorgue acceso o cambie el significado.
- **Tablas de mapeo versionadas** en el repo (roles legacy → roles nuevos, URLs legacy → permisos,
  enums legacy → catálogos).
- **Archivos:** copia de `uploads/` al almacenamiento del inquilino con verificación de checksum;
  faltantes a cuarentena.

### 5.4 Reglas conocidas de mapeo (de la revisión de la BD de producción)
- **Roles:** mapeo 1:1 por `id` + `nombre` (ambos deben coincidir o la corrida se detiene).
  Administrador recibe todos los permisos.
- **Permisos:** el legacy usa URLs de archivos PHP; se mapean por URL a permisos nuevos. Un permiso
  de carpeta (`/modulo/`) también daba acceso a los controladores AJAX del módulo.
- **Usuarios:** un rol por usuario; vínculo a exactamente una entidad (empleado/cliente/proveedor/aliado).
  `NombreUsuario` → `username`; correo tomado de la entidad vinculada.
  Las contraseñas (bcrypt `$2y$`) son compatibles: se conservan.
- **Correos repetidos entre usuarios** (persona que es empleado y aliado): el correo se asigna a una
  sola cuenta (prioridad: empleado); la otra entra con su usuario. Va al reporte.
- **Órdenes de servicio y órdenes de aliados** se migran a sus dominios separados. Sus IDs legacy
  se repiten entre tablas: por eso `legacy_source`.
- **Aliados y proveedores:** entidades del inquilino; se insertan en dos pasadas por su referencia circular.
- **Clientes duplicados** (misma identificación): no se fusionan en la migración; reporte para depurar después.
- **Fuera de alcance inicial:** GPS, notificaciones, alertas leídas, tokens, password resets, logs.

### 5.5 Seguridad
- La fuente legacy se usa siempre en **solo lectura**.
- Solo administradores centrales con permiso explícito pueden ejecutar corridas reales.
- Cada corrida queda auditada (quién, cuándo, qué módulos, resultado).
- Los datos personales de reportes y cuarentena solo se ven en el panel central.

### 5.6 Validación
- **A. Pre-análisis:** usuarios sin rol o con rol inexistente; usuarios con 0 o más de 1 entidad
  vinculada; rol que no corresponde al vínculo; usuarios duplicados; huérfanos en columnas sin FK;
  valores fuera de catálogo.
- **B. Durante:** contadores por paso; detener ante mapeos faltantes.
- **C. Conciliación:** conteos legacy = migrados + cuarentena + omitidos; sumas de control
  (tarifas, combustible, facturación) por mes; 100 % de registros con el `tenant_id` correcto;
  muestreo de registros; **equivalencia de permisos por usuario (bloqueante si alguien gana acceso)**.
- **D. Tests automatizados** de cada importador con datos de prueba (nunca datos reales en el repo).

---

## 6. Referencia: estado del legacy (resumen, sin datos personales)

| Aspecto | Hallazgo |
|---|---|
| Roles | 10 roles; 1 rol por usuario (`usuarios.rol_id`) |
| Permisos | 221 URLs de archivos PHP, generadas escaneando el disco |
| Autorización | URL en sesión + ~50 chequeos de `rol_id` escritos en el código |
| Usuarios | 184; mayoría aliados (112) y conductores (54); contraseñas bcrypt |
| Integridad | Sin huérfanos relevantes; algunas columnas sin FK; `usuarios.NombreUsuario` sin índice único |
| Riesgos del esquema | `ON DELETE CASCADE` de rol a usuarios; tablas duplicadas para aliados; enums largos con valores repetidos |

---

## 7. Decisiones pendientes

| # | Tema | Estado |
|---|---|---|
| 1 | Flujo de estados de las órdenes de aliados | Abierto |
| 2 | Estrategia de la API móvil (compatibilidad vs. nueva API) | Abierto (Fase 3) |
| 3 | Canal de notificaciones push (gestionado por Laravel) | Abierto |
| 4 | Fuente legacy en el panel: conexión directa vs. carga de dump | Abierto |
| 5 | Planes / límites por inquilino (si aplica) | Abierto |

## Registro de decisiones

| Fecha | Decisión |
|---|---|
| 2026-10-14 | PostgreSQL como base de datos; Inertia v3; tenancy single-database con `stancl/tenancy` |
| 2026-10-14 | Cada empresa tiene sus propios usuarios (`users.tenant_id`) |
| 2026-10-14 | RBAC con `spatie/laravel-permission`; **un rol por usuario** por ahora |
| 2026-10-14 | Administrador con todos los permisos |
| 2026-10-14 | Aliados y proveedores son terceros dentro del tenant, no tenants |
| 2026-10-14 | Órdenes de servicio y órdenes de aliados son dominios separados |
| 2026-10-14 | Login opción A: un campo "usuario o correo" |
| 2026-10-14 | Código y BD en inglés, interfaz en español |
| 2026-10-14 | Prioridad: panel central + usuarios/roles/permisos antes que órdenes |
| 2026-10-14 | No se migra ningún dato hasta la Fase 4; el sistema de migración se diseña desde el inicio y se opera desde el panel central |
| 2026-10-14 | Push notifications gestionadas por Laravel (canal por definir) |
