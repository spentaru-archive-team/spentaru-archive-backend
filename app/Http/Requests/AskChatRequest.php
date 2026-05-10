<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AskChatRequest extends FormRequest
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
        return [
            'x_trace_id' => 'required|string|max:255',
            'message' => 'required|string',
            'use_search' => 'nullable|boolean',
        ];
    }

    public function validationData(): array
    {
        return array_merge($this->all(), [
            'x_trace_id' => $this->header('X-Trace-Id'),
        ]);
    }

    public function attributes(): array
    {
        return [
            'x_trace_id' => 'header X-Trace-Id',
        ];
    }
}
