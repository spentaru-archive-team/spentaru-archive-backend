<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DecideRetentionRequest extends FormRequest
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
            'retention_status' => 'required|in:destroyed,retained,active',
            'retention_note' => 'nullable|string',
            'retention_due_date' => 'required_unless:retention_status,destroyed|nullable|date',
        ];
    }
}
