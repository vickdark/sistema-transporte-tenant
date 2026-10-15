<?php

namespace App\Http\Requests\Central;

use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Tenant $tenant */
        $tenant = $this->route('tenant');

        return TenantCompanyRules::rules(ignore: $tenant);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return TenantCompanyRules::attributes();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return TenantCompanyRules::messages();
    }
}
