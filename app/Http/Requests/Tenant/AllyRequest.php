<?php

namespace App\Http\Requests\Tenant;

use App\Models\Ally;

class AllyRequest extends ThirdPartyRequest
{
    protected function model(): string
    {
        return Ally::class;
    }

    protected function current(): ?Ally
    {
        $ally = $this->route('ally');

        return $ally instanceof Ally ? $ally : null;
    }

    public function attributes(): array
    {
        return [...parent::attributes(), 'nit' => 'NIT o cédula'];
    }
}
