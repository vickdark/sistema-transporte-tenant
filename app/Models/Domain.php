<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Domain as BaseDomain;

/**
 * Subdominio por el que se identifica un tenant (p. ej. "kemuel" → kemuel.{dominio central}).
 * Cada tenant tiene exactamente uno.
 *
 * @property int $id
 * @property string $domain
 * @property string $tenant_id
 */
class Domain extends BaseDomain {}
