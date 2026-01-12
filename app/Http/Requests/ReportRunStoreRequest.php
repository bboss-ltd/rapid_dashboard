<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportRunStoreRequest extends FormRequest
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
            'sprint_id' => ['nullable', 'integer', 'exists:sprints,id'],
            'status' => ['required', 'in:queued,running,success,failed'],
            'params' => ['required', 'json'],
            'snapshot_ref' => ['nullable', 'json'],
            'output_format' => ['nullable', 'string'],
            'output_path' => ['nullable', 'string'],
            'started_at' => ['nullable'],
            'finished_at' => ['nullable'],
            'error_message' => ['nullable', 'string'],
            'requested_by_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
