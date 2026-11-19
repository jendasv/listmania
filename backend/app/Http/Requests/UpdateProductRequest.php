<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    use ScopesToHousehold;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'category_id' => ['sometimes', 'nullable', 'integer', $this->categoryVisibleToHousehold()],
            'preferred_unit_id' => ['sometimes', 'nullable', 'integer', 'exists:units,id'],
            'preferred_quantity' => ['sometimes', 'nullable', 'numeric', 'min:0.01'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
            'barcode' => ['sometimes', 'nullable', 'string', 'alpha_dash:ascii', 'max:50'],
        ];
    }
}
