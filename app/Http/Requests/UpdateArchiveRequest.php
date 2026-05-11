<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateArchiveRequest extends FormRequest
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
            'title' => 'sometimes|required|string',
            'year' => 'sometimes|nullable|integer',
            'notes' => 'sometimes|nullable|string',
            'file' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg|mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,image/jpeg,image/png|max:10240',
            'event_id' => 'sometimes|nullable|integer|min:0|exists:events,id',
            'category_id' => 'sometimes|required|integer|min:0|exists:archive_categories,id',
            'subcategory_id' => 'sometimes|nullable|integer|min:0|exists:subcategories,id',
        ];
    }
}
