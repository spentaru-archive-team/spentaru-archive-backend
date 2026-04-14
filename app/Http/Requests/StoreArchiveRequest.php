<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreArchiveRequest extends FormRequest
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
            'title' => 'required|string',
            'year' => 'required|integer',
            'notes' => 'string|nullable',
            'file' => 'required|mimes:pdf,doc,docx,xls,xlsx|max:10240',
            'event_id' => 'integer|nullable|min:0',
            'category_id' => 'integer|min:0|required',
            'subcategory_id' => 'integer|min:0|nullable',
        ];
    }
}
