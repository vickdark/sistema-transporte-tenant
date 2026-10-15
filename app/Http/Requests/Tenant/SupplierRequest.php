<?php

namespace App\Http\Requests\Tenant;

use App\Models\Supplier;

class SupplierRequest extends ThirdPartyRequest
{
    protected function model(): string
    {
        return Supplier::class;
    }

    protected function current(): ?Supplier
    {
        $supplier = $this->route('supplier');

        return $supplier instanceof Supplier ? $supplier : null;
    }
}
