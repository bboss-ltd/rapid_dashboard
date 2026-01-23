<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SprintRemakeUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'estimate_points' => ['nullable', 'integer', 'min:0'],
            'label_name' => ['nullable', 'string', 'max:255'],
            'label_points' => ['nullable', 'integer', 'min:0'],
            'reason_label' => ['nullable', 'string', 'max:255'],
            'reason_label_color' => ['nullable', 'string', 'max:255'],
        ];
    }
}
