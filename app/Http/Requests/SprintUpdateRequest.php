<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SprintUpdateRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'starts_at' => ['required'],
            'ends_at' => ['required'],
            'closed_at' => ['nullable'],
            'trello_board_id' => ['nullable', 'string'],
            'done_list_ids' => ['nullable', 'json'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
