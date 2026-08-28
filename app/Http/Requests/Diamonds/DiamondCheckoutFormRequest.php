<?php

namespace App\Http\Requests\Diamonds;

use Illuminate\Foundation\Http\FormRequest;

class DiamondCheckoutFormRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'diamonds' => ['required', 'integer', 'min:500', 'max:100000', 'multiple_of:100'],
        ];
    }
}
