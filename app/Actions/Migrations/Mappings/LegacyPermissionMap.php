<?php

namespace App\Actions\Migrations\Mappings;

use App\Enums\Permission;

/**
 * Permisos legacy (URLs de archivos PHP) → permisos de la empresa.
 *
 * En el legacy una URL de página (`/usuarios/create.php`) solo abre esa página, y un permiso de
 * carpeta (`/usuarios/`) abre el index y todos los controladores AJAX del módulo, que son los que
 * guardan y borran. Por eso una acción solo se otorga si el rol podía completarla en el legacy:
 * nadie gana acceso con la migración.
 *
 * Toda URL de la fuente debe estar clasificada aquí; una desconocida detiene la corrida. Las de
 * módulos aún no construidos no otorgan nada y van al reporte: cada módulo de la Fase 2 las
 * mueve a GRANTS cuando exista.
 */
class LegacyPermissionMap
{
    /**
     * Permiso => alternativas; cada alternativa es la lista de URLs legacy que el rol debía tener.
     *
     * @var array<string, list<list<string>>>
     */
    private const GRANTS = [
        'users.view' => [['/usuarios/index.php'], ['/usuarios/']],
        'users.create' => [['/usuarios/create.php', '/usuarios/']],
        'users.update' => [['/usuarios/update.php', '/usuarios/']],
        'users.delete' => [['/usuarios/']],
        'roles.view' => [['/roles/index.php'], ['/roles/']],
        'roles.manage' => [['/roles/create.php', '/roles/update.php', '/roles/']],
        // Aliados (legacy `proveedores_aliados`) y proveedores: convertir/revertir son controladores
        // de la carpeta que se llaman desde el listado, así que basta el permiso de carpeta.
        'allies.view' => [['/proveedores_aliados/index.php'], ['/proveedores_aliados/']],
        'allies.create' => [['/proveedores_aliados/create.php', '/proveedores_aliados/']],
        'allies.update' => [['/proveedores_aliados/update.php', '/proveedores_aliados/']],
        'allies.link' => [['/proveedores_aliados/']],
        'suppliers.view' => [['/proveedores/index.php'], ['/proveedores/']],
        'suppliers.create' => [['/proveedores/create.php', '/proveedores/']],
        'suppliers.update' => [['/proveedores/update.php', '/proveedores/']],
        'suppliers.link' => [['/proveedores/']],
        // Clientes: inhabilitar se llama desde el listado (controlador de la carpeta) y habilitar
        // desde la pantalla de inactivos, así que hacen falta ambos.
        'clients.view' => [['/clientes/index.php'], ['/clientes/']],
        'clients.create' => [['/clientes/create.php', '/clientes/']],
        'clients.update' => [['/clientes/update.php', '/clientes/']],
        'clients.deactivate' => [['/clientes/', '/clientes/inactivos/index.php'], ['/clientes/', '/clientes/inactivos/']],
        // Rutas: el legacy eliminaba desde el listado (controlador de la carpeta); aquí se inactiva.
        'routes.view' => [['/rutas/index.php'], ['/rutas/']],
        'routes.create' => [['/rutas/create.php', '/rutas/']],
        'routes.update' => [['/rutas/update.php', '/rutas/']],
        'routes.deactivate' => [['/rutas/']],
        // Contratos: crear y editar guardan con controladores de la carpeta.
        'contracts.view' => [['/contratos/index.php'], ['/contratos/']],
        'contracts.create' => [['/contratos/create.php', '/contratos/']],
        'contracts.update' => [['/contratos/update.php', '/contratos/']],
        'ally_contracts.view' => [['/contratos_aliados/index.php'], ['/contratos_aliados/']],
        'ally_contracts.create' => [['/contratos_aliados/create.php', '/contratos_aliados/']],
        'ally_contracts.update' => [['/contratos_aliados/update.php', '/contratos_aliados/']],
    ];

    /**
     * URLs conocidas que por sí solas no otorgan ningún permiso.
     *
     * @var list<string>
     */
    private const WITHOUT_GRANT = [
        '/usuarios/show.php',
        '/proveedores/show.php',
        '/proveedores_aliados/show.php',
        '/clientes/show.php',
        '/clientes/inactivos/index.php',
        '/clientes/inactivos/',
        '/clientes/inactivos/show.php',
        '/rutas/show.php',
        '/contratos/show.php',
        '/contratos/js/',
        '/contratos_aliados/show.php',
        '/contratos_aliados/js/',
        '/contratos_aliados/check_aliado_session.php',
        '/contratos_aliados/select_aliado.php',
    ];

    /**
     * Primer segmento de la URL => módulo de la Fase 2 que la va a mapear.
     *
     * @var array<string, string>
     */
    private const PENDING_MODULES = [
        'clientesdash' => 'Portal de clientes',
        'combustible' => 'Combustible',
        'conductor' => 'Operación del conductor',
        'conductores' => 'Empleados y conductores',
        'empleados' => 'Empleados y conductores',
        'estadisticas' => 'Reportes y estadísticas',
        'gps' => 'GPS',
        'mantenimientos' => 'Mantenimientos',
        'novedades' => 'Novedades',
        'ordenServicio' => 'Órdenes de servicio',
        'proveedoresdash' => 'Portal de proveedores',
        'proveedoresdash_aliados' => 'Portal de aliados',
        'reportes_gerenciales' => 'Reportes gerenciales',
        'vehiculos' => 'Vehículos',
    ];

    /**
     * Primer segmento de URLs que no corresponden a un permiso de la aplicación nueva
     * (recursos de gráficos, login, páginas de prueba y documentación).
     *
     * @var list<string>
     */
    private const IGNORED = [
        'charts',
        'documentacion',
        'index.php',
        'login',
        'pruebagps.php',
    ];

    public function isKnown(string $url): bool
    {
        return $this->isMapped($url)
            || $this->pendingModule($url) !== null
            || in_array(self::firstSegment($url), self::IGNORED, true);
    }

    /**
     * Módulo de la Fase 2 que todavía no existe y al que pertenece la URL.
     */
    public function pendingModule(string $url): ?string
    {
        return self::PENDING_MODULES[self::firstSegment($url)] ?? null;
    }

    /**
     * Permisos de la empresa que corresponden a las URLs que tenía un rol legacy.
     *
     * @param  list<string>  $legacyUrls
     * @return list<string>
     */
    public function permissionsFor(array $legacyUrls): array
    {
        $granted = [];

        foreach (self::GRANTS as $permission => $alternatives) {
            foreach ($alternatives as $requiredUrls) {
                if (array_diff($requiredUrls, $legacyUrls) === []) {
                    $granted[] = Permission::from($permission)->value;
                    break;
                }
            }
        }

        return $granted;
    }

    private function isMapped(string $url): bool
    {
        if (in_array($url, self::WITHOUT_GRANT, true)) {
            return true;
        }

        foreach (self::GRANTS as $alternatives) {
            foreach ($alternatives as $requiredUrls) {
                if (in_array($url, $requiredUrls, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function firstSegment(string $url): string
    {
        return explode('/', ltrim($url, '/'))[0];
    }
}
