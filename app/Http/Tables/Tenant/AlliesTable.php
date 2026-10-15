<?php

namespace App\Http\Tables\Tenant;

use App\Models\Ally;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends ThirdPartiesTable<Ally>
 */
class AlliesTable extends ThirdPartiesTable
{
    public function title(): string
    {
        return 'Aliados';
    }

    protected function baseQuery(): Builder
    {
        return Ally::query()->with('supplier:id,name,ally_id');
    }

    protected function whereMixed(Builder $query, bool $mixed): Builder
    {
        return $mixed ? $query->whereHas('supplier') : $query->whereDoesntHave('supplier');
    }

    /**
     * @param  Ally  $model
     */
    protected function twin(Model $model): ?Supplier
    {
        return $model->supplier;
    }

    protected function twinRoute(): string
    {
        return 'suppliers.show';
    }

    protected function nitLabel(): string
    {
        return 'NIT o cédula';
    }
}
