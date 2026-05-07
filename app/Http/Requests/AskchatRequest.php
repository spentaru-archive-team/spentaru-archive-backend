<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AskchatRequest extends FormRequest
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
            'message' => 'required|string',
            'use_search' => 'nullable|boolean',
            'temperature' => 'nullable|numeric|between:0,2',
            'context' => [
                'nullable',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) && ! is_array($value)) {
                        $fail("The {$attribute} field must be a string, array, or object.");
                    }
                },
            ],
        ];
    }
}
