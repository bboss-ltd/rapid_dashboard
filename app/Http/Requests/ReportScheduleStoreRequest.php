<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportScheduleStoreRequest extends FormRequest
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
            'report_definition_id' => ['required', 'integer', 'exists:report_definitions,id'],
            'name' => ['required', 'string'],
            'is_enabled' => ['required'],
            'cron' => ['required', 'string'],
            'timezone' => ['required', 'string'],
            'default_params' => ['required', 'json'],
            'last_ran_at' => ['nullable'],
            'next_run_at' => ['nullable'],
        ];
    }
}
