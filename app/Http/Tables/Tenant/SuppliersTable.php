<?php

namespace App\Http\Tables\Tenant;

use App\Models\Ally;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends ThirdPartiesTable<Supplier>
 */
class SuppliersTable extends ThirdPartiesTable
{
    public function title(): string
    {
        return 'Proveedores';
    }

    protected function baseQuery(): Builder
    {
        return Supplier::query()->with('ally:id,name');
    }

    protected function whereMixed(Builder $query, bool $mixed): Builder
    {
        return $mixed ? $query->whereNotNull('ally_id') : $query->whereNull('ally_id');
    }

    /**
     * @param  Supplier  $model
     */
    protected function twin(Model $model): ?Ally
    {
        return $model->ally;
    }

    protected function twinRoute(): string
    {
        return 'allies.show';
    }

    protected function nitLabel(): string
    {
        return 'NIT';
    }
}
