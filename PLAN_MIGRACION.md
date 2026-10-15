# Plan de reconstrucción y migración — sisTransporte → Laravel multi-tenant

> Documento vivo. Se ajusta a medida que avanzamos; cada cambio de decisión se anota en el
> [registro de decisiones](#registro-de-decisiones).
>
> **Estado:** Fase 0 y Fase 1 terminadas. Fase 2 en curso: 2.1 (configuración), 2.2 (aliados y proveedores), 2.3 (clientes), 2.4 (rutas) y 2.5 (contratos) terminadas.

---

## 1. Contexto

- **Sistema legacy:** `sisTransporte/` (PHP sin framework + MySQL 8, una sola empresa: Transportes Kemuel S.A.S.).
  Es solo referencia: **no se modifica nada dentro de esa carpeta.**
- **Sistema nuevo:** este proyecto. Laravel 12, Inertia v3 + Vue 3 + TypeScript, PostgreSQL,
  multi-tenant con **un schema de PostgreSQL por empresa** (`stancl/tenancy`, `PostgreSQLSchemaManager`).
- **Objetivo:** reconstruir la lógica de negocio con un esquema normalizado y, en paralelo,
  construir un sistema de migración de datos operable desde el panel del administrador central.

## 2. Principios

1. **Primero se construye, después se migra.** Ningún dato legacy entra al sistema hasta que
   el módulo correspondiente exista, tenga tests y su importador esté validado.
2. **La migración se diseña desde el día uno.** Cada módulo que se construye entrega también
   su importador (ver [§5](#5-sistema-de-migración-de-datos)).
3. **Aislamiento por tenant en la base de datos.** Cada empresa tiene su propio schema
   (`tenant_<subdominio>`); sus tablas de negocio no llevan `tenant_id` y una FK no puede cruzar a otra empresa.
   Las migraciones de empresa van en `database/migrations/tenant` y se aplican con `php artisan tenants:migrate`.
   Las tablas del panel (empresas, subdominios, administradores, migraciones, sesiones, caché, colas)
   viven en el schema central (`public`); sus modelos usan `CentralConnection`.
4. **Autorización por permisos, nunca por rol.** `can()`, Policies y middleware `permission:`.
   Ningún `if rol == X` en el código.
5. **Idioma:** código, tablas y columnas en inglés; interfaz en español.
6. **Convenciones Laravel:** Form Requests, Policies, Enums para estados, Actions/servicios para
   la lógica, tests de Pest en cada módulo.

## 3. Arquitectura: dos mundos

| | Panel central (landlord) | Aplicación del tenant |
|---|---|---|
| Dominio | `sistema-transporte-tenant.test` (dominios centrales) | `{subdominio}.{TENANT_BASE_DOMAIN}`: local `demo.test`; en BD solo se guarda `demo` |
| Datos | Schema central `public` | Schema propio `tenant_<subdominio>` (se crea y migra al crear la empresa; se borra si se elimina) |
| Usuarios | Administradores de la plataforma (`central_users`) | Usuarios de cada empresa (`users` en el schema de la empresa) |
| Funciones | Crear/gestionar inquilinos, dominios, planes; **ejecutar migraciones**; monitoreo | Operación del negocio: órdenes, FUEC, flota, conductores… |
| Rutas | `routes/web.php` (solo en el dominio central principal, el de `APP_URL`) | `routes/tenant.php` |
| Auth | Guard `central` (tabla `central_users`); el primero se crea con `php artisan central:create-user` | Guard `web` (tabla `users`) |

> **Desarrollo local (Herd en Windows):** con `HERD_AUTO_LINK=true` en `.env`, al crear una empresa o
> cambiar su subdominio el panel ejecuta `herd link`/`herd unlink` (`App\Actions\Tenants\RegisterLocalTenantHost`).
> Si falla, muestra una advertencia con el comando a correr a mano. El subdominio no puede coincidir
> con otro sitio de Herd (p. ej. `transporteskemuel` ya es otro proyecto).
>
> **Tests:** corren contra PostgreSQL (`sistransporte_test`, ver `phpunit.xml`) con `DatabaseTruncation`;
> `tests/TestCase.php` borra los schemas `tenant_*` al terminar cada test.

---

## 4. Fases

Leyenda: ⬜ pendiente · 🟨 en curso · ✅ terminado

### Fase 0 — Panel central y gestión de inquilinos ✅
- ✅ Modelo y guard de **administradores centrales** (tabla `central_users`, guard `central`, login propio en el dominio central, comando `central:create-user`).
- ✅ CRUD de **inquilinos**: nombre, NIT (único), estado, correo, teléfono, dirección, ciudad. Son columnas reales de `tenants`, no el JSON `data`.
- ✅ **Eliminar inquilino** (`App\Actions\Tenants\DeleteTenant`): borra la empresa, su subdominio, sus corridas de migración, su schema completo y, en local, su sitio de Herd. Se confirma escribiendo el subdominio.
- ✅ **Usuario inicial**: al crear la empresa se elige entre enviar invitación por correo o definir la contraseña en el panel (cuenta activa de una vez). Desde la ficha se editan nombre y correo (único dentro del schema), se cambia la contraseña o se envía una invitación nueva (p. ej. tras cambiar el correo). `tenants.initial_user_id` guarda cuál es; `users.invited_at` / `invitation_accepted_at` registran la invitación.
- ✅ **Subdominio** por inquilino (uno solo, editable): se guarda solo `demo` en `domains.domain` y se identifica con `InitializeTenancyBySubdomainOfBaseDomain` sobre `tenancy.tenant_base_domain`. Sin dominios adicionales.
- ✅ **Schema por empresa**: al crear el tenant se crea `tenant_<subdominio>` y se migra (síncrono); al eliminarlo se borra; si cambia el subdominio, el schema se renombra (`ALTER SCHEMA`, instantáneo y transaccional). El subdominio admite hasta 56 caracteres para que el nombre del schema quepa en los 63 de PostgreSQL. El alta no va en una transacción (la migración usa otra conexión); si falla a mitad, se elimina el tenant.
- ✅ **Alta de inquilino** (`App\Actions\Tenants\CreateTenant`): crea tenant + subdominio (el schema se siembra con permisos y roles base) + primer usuario con el rol Administrador, invitado por correo o con contraseña definida en el panel.
- ✅ Suspender / reactivar inquilino: middleware `EnsureTenantIsActive` responde 403 con la página "Acceso suspendido" en todas las rutas del tenant; no se borra nada.
- ✅ Esqueleto del **módulo de migraciones**: tablas `migration_sources` (credenciales cifradas, nunca se envían al frontend), `migration_runs`, `migration_run_steps`, `migration_issues`; pantallas de fuentes (registrar/eliminar), listado de corridas y detalle de corrida. Aún sin importadores ni ejecución.

### Fase 1 — Identidad, roles y permisos (por tenant) ✅
- ✅ `spatie/laravel-permission` 6 **sin modo teams**: sus tablas van en las migraciones de empresa, cada schema tiene sus propios roles y permisos. Guard fijo `web` (`User::GUARD`); la caché de permisos usa una clave por empresa (`TenancyServiceProvider`).
- ✅ **Catálogo de permisos** en `App\Enums\Permission` (`users.view|create|update|delete`, `roles.view|manage`). `php artisan tenants:sync-access` (también al crear la empresa) crea los nuevos, borra los que salieron del catálogo y le da todo al Administrador. Cada módulo de la Fase 2 agrega sus permisos al enum.
- ✅ **Roles base** (`App\Enums\BaseRole`), identificados por `roles.base_key` para que la empresa pueda renombrarlos. Administrador: protegido, todos los permisos, no se edita ni se borra. Los demás nacen sin permisos y cada empresa los configura.
- ✅ **Un rol por usuario** en 3 niveles: índice único `(model_id, model_type)` en `model_has_roles`, servicio `App\Actions\Users\AssignUserRole` y tests. Además nunca queda una empresa sin Administrador (cambio de rol, borrado desde la gestión o desde el propio perfil).
- ➡️ **Usuario vinculado a un actor** (`employee_id` | `client_id` | `supplier_id` | `ally_id`, exactamente uno con CHECK en BD): pasa a la Fase 2, porque necesita las tablas de esas entidades. Lo completa el importador de cada entidad releyendo `usuarios` en la fuente.
- ✅ **Login "usuario o correo"**: `users.username` obligatorio y único (formato `App\Rules\Username`, sin "@"); `users.email` opcional y único. Sin registro público. La recuperación de contraseña sigue siendo por correo; quien no tiene correo la recibe del administrador.
- ✅ Pantallas: gestión de usuarios (invitación o contraseña, rol), roles con matriz de permisos por módulo, menú filtrado por permisos, página "sin acceso asignado". Autorización con `UserPolicy` / `RolePolicy` (solo permisos).
- ✅ **Motor de corridas** (§5.3): desde el panel central se elige fuente, empresa, módulos y modo. Un job (`ExecuteMigrationRun`) verifica el NIT de `configuracion_empresa` contra el de la empresa y corre los pasos en orden dentro del schema. En modo real, una transacción por paso; en simulación, toda la corrida en una transacción que se revierte. Progreso en vivo, cancelación entre lotes, cuarentena y advertencias en `migration_issues`. Una corrida activa por empresa; un módulo exige que sus dependencias vayan en la corrida o ya estén migradas en una corrida real.
- ✅ Importadores **roles** y **usuarios** (§5.4), idempotentes por `legacy_source` + `legacy_id` (columnas nuevas en `users` y `roles`). Mapeos versionados en `app/Actions/Migrations/Mappings`.
- ✅ **Listados estándar** (`App\Http\Tables\DataTable` + componente `DataTable.vue`): búsqueda, filtros, orden por columna y paginación en el servidor; exportación a Excel (`openspout`) y PDF (`dompdf`) con los mismos filtros y orden. En móvil cada fila es una tarjeta; acciones con color por tipo (ver, editar, eliminar). **Todo listado nuevo de la Fase 2 se construye con este patrón.**

### Fase 2 — Módulos de negocio (por tenant) ⬜
Orden por dependencias. Cada módulo incluye: migraciones, modelos, Policies, pantallas (listados con `DataTable`), tests e **importador** (y sus URLs legacy en `LegacyPermissionMap`).

- ⬜ **Usuario vinculado a un actor** (viene de la Fase 1): columnas + CHECK en `users` cuando existan empleados, clientes, proveedores y aliados.

| # | Módulo | Notas |
|---|---|---|
| 2.1 ✅ | Configuración de la empresa | Razón social y NIT siguen en el panel central (solo lectura en la empresa). En el schema (`company_profiles`, una fila): contacto para documentos, tipo de servicio, habilitación del FUEC (territorial 3 dígitos, resolución 4, año 2), logo y firma (privados por empresa; la firma solo la ve quien tiene `company.manage`). Importador `company` desde `configuracion_empresa` |
| 2.2 ✅ | Terceros: aliados y proveedores | Igual que el legacy: dos módulos gemelos (nombre obligatorio; nombre y NIT no se repiten dentro del módulo), sin eliminar ni estado. **Convertir** (crea o reutiliza el gemelo con el mismo NIT y queda mixto) y **Revertir** (rompe el vínculo, ambos siguen) en `App\Actions\ThirdParties\LinkAllyAndSupplier`, sin triggers; el vínculo vive solo en `suppliers.ally_id` (único). `users.ally_id` / `users.supplier_id`. Importador `third_parties` (mixtos solo si son recíprocos; repetidos se migran y se reportan) |
| 2.3 ✅ | Clientes | Igual que el legacy: persona/empresa, código de sucursal, dueño empresa o aliado (`ally_id`): cada usuario ve solo los clientes de su dueño (también inactivos; el legacy los mostraba todos). Validaciones del editar legacy en crear y editar; identificación única en toda la base salvo que no cambie al editar. Inhabilitar/habilitar, sin eliminar. `users.client_id`. Importador `clients` (dueño desconocido → cuarentena; repetidos y formatos inválidos se migran y se reportan) |
| 2.4 ✅ | Rutas | Igual que el legacy: inicio, fin (el nombre es "Inicio - Fin"), tipo, estado y descripción, todos obligatorios. Se corrige que el estado no se podía cambiar al editar. No se eliminan: inhabilitar las pasa a Inactivas (listado aparte). No se repiten inicio y fin sin distinguir mayúsculas ni espacios. Modelo `TransportRoute` (tabla `routes`). La creación rápida desde la orden (reutiliza la ruta si ya existe) se conecta en 2.8. Importador `routes`: las repetidas se **unifican** (entra la de id más antiguo; las demás quedan en `legacy_aliases` para que el importador de órdenes resuelva su ruta con `LegacyAlias::resolve`) |
| 2.5 ✅ | Contratos propios y contratos de aliados | Módulos separados (rutas y permisos `contracts.*` / `ally_contracts.*`) sobre una tabla `contracts` (`kind` own/ally, `ally_id`). Como el legacy: listado agrupado por cliente, objeto de lista fija, fin hasta el 31/dic del año de inicio, responsable obligatorio, sin eliminar; en los de aliados un usuario de la empresa elige primero el aliado (sesión). Se editan vigentes y futuros (el legacy solo vigentes). "Un contrato a la vez": las fechas no se cruzan con otro del mismo cliente. `number` = número del FUEC, consecutivo por clase (migrados conservan el id legacy). Importador `contracts` |
| 2.6 | Vehículos | Documentos, fotos, partes (catálogo en tabla), convenios. Igual que 2.7: vehículos de aliados/proveedores mixtos en `LinkAllyAndSupplier`; listado y ficha de terceros muestran sus vehículos y conductores |
| 2.7 | Empleados y conductores | "Conductor" es una función del empleado, no un rol. Documentos con vencimiento. Al convertir/revertir un aliado o proveedor, sus conductores se asocian/desasocian al otro perfil (acción `LinkAllyAndSupplier`, reemplaza el trigger legacy) |
| 2.8 | **Órdenes de servicio** | Núcleo. Máquina de estados explícita, pasajeros, apoyo, observaciones, kilometrajes. Ruta: `LegacyAlias::resolve` en la migración; selector solo de rutas activas y creación rápida reutilizando la existente. Contrato creado desde la orden con las mismas reglas de 2.5 (si el cliente tiene uno vigente, la orden lo usa) |
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
  único `(legacy_source, legacy_id)` dentro del schema de la empresa. Repetir una corrida actualiza, no duplica.
- **Escritura en el schema destino:** cada paso corre dentro de `$tenant->run()`; las tablas de control
  (`migration_*`) se escriben siempre por la conexión central.
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
- **Roles sin rol base equivalente** (`Creador`, `Administrativo 2`): se crean como roles propios de la empresa con el nombre legacy.
- **Permisos por URL:** una acción se otorga solo si el rol podía completarla en el legacy (la página **y** el permiso de carpeta que abre los controladores AJAX). Las URLs de módulos aún no construidos no otorgan nada y van al reporte; cada módulo de la Fase 2 las agrega al mapeo y una nueva corrida de roles las asigna.
- **Usuarios:** un rol por usuario; vínculo a exactamente una entidad (empleado/cliente/proveedor/aliado). Se importan todos; el vínculo al actor lo completa el importador de cada entidad (Fase 2), releyéndolo de la fuente.
- **Choques** con una cuenta que ya existe en la empresa (mismo usuario o correo, p. ej. el usuario inicial): cuarentena, la cuenta existente no se toca.
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
  (tarifas, combustible, facturación) por mes; nada escrito fuera del schema de la empresa destino;
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
| 4 | Fuente legacy en el panel: conexión directa vs. carga de dump | Resuelto (2026-10-15): conexión directa de solo lectura a un espejo de la BD de producción; la carga de dump no se implementa por ahora |
| 5 | Planes / límites por inquilino (si aplica) | Abierto |
| 6 | Dominio de producción y si el panel va en el dominio raíz o en `admin.` | Abierto |

---

## 8. Despliegue en producción (Linux)

En producción **no se crea nada por empresa**: ni registro DNS, ni virtual host, ni certificado. Todo
se configura una sola vez con comodines y cada empresa nueva funciona en cuanto se crea en el panel.
(Ejemplo con `sistransporte.com`; reemplazar por el dominio real.)

**1. DNS (una vez)**

| Tipo | Nombre | Valor |
|---|---|---|
| A | `sistransporte.com` | IP del servidor (panel central) |
| A | `*.sistransporte.com` | IP del servidor (todas las empresas) |

Si el panel va en `admin.sistransporte.com`, basta el registro comodín; `admin` ya es un subdominio reservado.

**2. `.env`**
```
APP_URL=https://sistransporte.com          # host del panel central
TENANT_BASE_DOMAIN=sistransporte.com       # kemuel → kemuel.sistransporte.com
HERD_AUTO_LINK=false                       # Herd es solo para desarrollo local
SESSION_SECURE_COOKIE=true                 # SESSION_DOMAIN vacío: cada host tiene su propia cookie
DB_CONNECTION=pgsql
```

**3. PostgreSQL:** el usuario de la app necesita crear schemas en su base (no CREATEDB ni superusuario):
`GRANT CREATE ON DATABASE sistransporte TO app_user;`

**4. Certificado SSL comodín (Let's Encrypt):** un comodín solo se emite con la validación DNS-01, así que
se usa el plugin de certbot del proveedor DNS (p. ej. Cloudflare):
```
certbot certonly --dns-cloudflare --dns-cloudflare-credentials /root/.secrets/cloudflare.ini \
  -d sistransporte.com -d '*.sistransporte.com'
```
La renovación es automática (timer de certbot). Forge y Ploi también lo hacen con su opción de subdominios comodín.

**5. Nginx (un solo server block para panel y empresas)**
```nginx
server {
    listen 80;
    server_name sistransporte.com *.sistransporte.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name sistransporte.com *.sistransporte.com;
    root /var/www/sistransporte/current/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/sistransporte.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/sistransporte.com/privkey.pem;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }
}
```
Laravel decide por el host: el dominio de `APP_URL` es el panel; `{subdominio}.{TENANT_BASE_DOMAIN}` es una
empresa; cualquier otro subdominio responde 404.

**6. En cada despliegue**
```
php artisan migrate --force           # schema central
php artisan tenants:migrate --force   # todos los schemas de empresa
php artisan tenants:sync-access       # catálogo de permisos y roles base en cada empresa
php artisan config:cache && php artisan route:cache
php artisan queue:restart
```
Además: `queue:work` bajo Supervisor y el cron de `schedule:run`. Si hay un proxy o CDN delante (Cloudflare,
balanceador), configurar los proxies de confianza para que Laravel vea el host y el esquema reales.

**7. Respaldos:** `pg_dump` de toda la base, o de una sola empresa con `pg_dump -n tenant_<subdominio>`.

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
| 2026-10-14 | Fase 0: el panel central solo se registra en el dominio central principal (evita nombres de ruta duplicados) |
| 2026-10-14 | Inquilinos solo por subdominio sobre un dominio base configurable (`TENANT_BASE_DOMAIN`, local `test` → `demo.test`); se quitan los dominios adicionales |
| 2026-10-14 | Fase 0: el primer usuario de un inquilino se invita con un enlace firmado de un solo uso (no con el broker de restablecimiento, cuya tabla no distingue tenant) |
| 2026-10-14 | Fase 0: los inquilinos no se borran desde el panel; solo se suspenden |
| 2026-10-14 | **Reemplaza a single-database:** un schema de PostgreSQL por empresa (`tenant_<subdominio>`); sin `tenant_id` en las tablas de negocio; Spatie sin modo teams |
| 2026-10-14 | Sesiones, caché y colas en el schema central (conexión fijada en config) |
| 2026-10-14 | Producción: DNS, Nginx y SSL comodín; nada se crea por empresa. En local, `herd link` automático (`HERD_AUTO_LINK`) |
| 2026-10-14 | Tests contra PostgreSQL real (`sistransporte_test`) con `DatabaseTruncation` |
| 2026-10-14 | **Reemplaza a "no se borran":** los inquilinos se pueden eliminar desde el panel con todos sus datos (confirmando el subdominio); suspender sigue siendo la opción reversible |
| 2026-10-14 | Interfaz en español: `APP_LOCALE=es` con traducciones propias en `lang/es` (sin paquetes); los tests fijan `es` en `phpunit.xml` |
| 2026-10-14 | Schema con nombre legible `tenant_<subdominio>` (se renombra si cambia el subdominio) en vez del UUID |
| 2026-10-14 | El usuario inicial puede recibir invitación o contraseña definida por el panel, en el alta y en la edición |
| 2026-10-14 | Todas las confirmaciones de la interfaz con SweetAlert2 (`useConfirm`); dependencia `sweetalert2` aprobada |
| 2026-10-14 | Fase 1: sin registro público; los usuarios los crea el administrador de la empresa (o el panel central el inicial) |
| 2026-10-14 | Fase 1: los roles base distintos de Administrador nacen sin permisos; cada empresa los configura en la matriz |
| 2026-10-14 | Fase 1: el importador de usuarios y roles se hace junto con el motor de corridas de migración |
| 2026-10-15 | Importador de roles: los roles legacy sin rol base equivalente se crean como roles propios de la empresa |
| 2026-10-15 | Importador de usuarios: se importan todos los usuarios ya en la Fase 1; el vínculo al actor se completa en la Fase 2 |
| 2026-10-15 | Un usuario legacy que choca con una cuenta existente (usuario o correo) va a cuarentena |
| 2026-10-15 | Fuente de pruebas: espejo de la BD de producción en el servidor (lo prepara el usuario) |
| 2026-10-15 | Listados: tabla resuelta en el servidor (búsqueda, filtros, orden, paginación) y exportación a Excel/PDF con los mismos filtros; dependencias `openspout/openspout` y `barryvdh/laravel-dompdf` aprobadas |
| 2026-10-15 | El vínculo usuario↔actor pasa de la Fase 1 a la Fase 2 (necesita las tablas de las entidades) |
| 2026-10-15 | 2.1: razón social y NIT son del panel central; el resto de la configuración vive en el schema de la empresa y la edita quien tenga `company.manage` |
| 2026-10-15 | 2.1: logo y firma del FUEC se suben como imágenes PNG/JPG (máx. 2 MB) a almacenamiento privado por empresa; no se aceptan SVG |
| 2026-10-15 | 2.2: aliados y proveedores funcionan como en el legacy; se quita el estado (Activo/Inactivo) porque ninguna pantalla lo usaba |
| 2026-10-15 | 2.2: proveedores repetidos (mismo NIT) se migran todos y se reportan; los NIT se guardan sin espacios (en el legacy un espacio al final hacía que Convertir duplicara en vez de vincular) |
| 2026-10-15 | 2.2: permisos `allies.*` y `suppliers.*` separados en ver, crear, editar y convertir/revertir |
| 2026-10-15 | 2.3: clientes con la visibilidad del legacy (aliado: solo los suyos; internos: solo los de la empresa), aplicada también a inactivos |
| 2026-10-15 | 2.3: identificación única en toda la base como el legacy, ahora también al crear; no se revalida si no cambia al editar (hay 416 clientes repetidos en producción) |
| 2026-10-15 | 2.3: las validaciones del editar del legacy aplican también al crear; los clientes no se eliminan, solo se inhabilitan |
| 2026-10-15 | 2.4: las rutas conservan el estado (Activa, En mantenimiento, Inactiva) y ahora se puede cambiar; no se eliminan, se inactivan como los clientes |
| 2026-10-15 | 2.4: no se permiten dos rutas con el mismo inicio y fin (sin distinguir mayúsculas ni espacios); en la migración las repetidas se unifican en una sola y sus ids legacy apuntan a ella (`legacy_aliases`) |
| 2026-10-15 | Orden de la Fase 2: contratos pasa a 2.5 y empleados y conductores a 2.7 |
| 2026-10-15 | 2.5: contratos de aliados con selección de aliado como el legacy; responsable obligatorio; se editan vigentes y futuros |
| 2026-10-15 | 2.5: un contrato no puede cruzar fechas con otro del mismo cliente (reemplaza "no crear si hay uno vigente", que impedía registrar la renovación por adelantado) |
| 2026-10-15 | 2.5: número de contrato del FUEC consecutivo por clase, continuando desde el último id legacy |
| 2026-10-15 | 2.8: el contrato creado desde la orden aplica las mismas reglas que la pantalla de contratos |
